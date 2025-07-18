"""ZipPicks Web Application - FastAPI + Jinja2 Implementation."""

import os
import json
from pathlib import Path
from datetime import datetime
from typing import Optional, Dict, Any

from fastapi import FastAPI, Request, Form, HTTPException, status
from fastapi.responses import HTMLResponse, RedirectResponse, JSONResponse
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
from pydantic import BaseModel
from pydantic_settings import BaseSettings

# Local imports
from data.cities import get_city_name, get_all_cities, is_valid_city, get_city_by_slug
from data.categories import get_category_name, get_all_categories, is_valid_category, get_category_by_slug
from utils.cache import get_cache, format_cache_timestamp
from services.generator import get_master_critic_service, RestaurantData


class Settings(BaseSettings):
    """Application settings."""
    app_name: str = "ZipPicks"
    debug: bool = True
    cache_ttl: int = 86400  # 24 hours
    api_timeout: int = 30
    
    class Config:
        env_file = ".env"


# Initialize app
settings = Settings()
app = FastAPI(
    title=settings.app_name,
    debug=settings.debug,
    docs_url="/api/docs" if settings.debug else None
)

# Mount static files
app.mount("/static", StaticFiles(directory="static"), name="static")

# Configure templates
templates = Jinja2Templates(directory="templates")

# Initialize services
cache = get_cache()
master_critic = get_master_critic_service()


# Template context processor
def get_base_context(request: Request) -> Dict[str, Any]:
    """Get base context for all templates."""
    return {
        "request": request,
        "app_name": settings.app_name,
        "current_year": datetime.now().year,
        "cities": get_all_cities(),
        "categories": get_all_categories()
    }


@app.get("/", response_class=HTMLResponse)
async def home(request: Request):
    """Homepage with city and category selection."""
    context = get_base_context(request)
    context.update({
        "page_title": "Find Your Perfect Restaurant",
        "meta_description": "Discover the best restaurants in your city with AI-powered recommendations from ZipPicks Master Critic."
    })
    return templates.TemplateResponse("home.html", context)


@app.post("/generate-list")
async def generate_list(
    request: Request,
    city: str = Form(...),
    category: str = Form(...)
):
    """Handle form submission and redirect to results."""
    # Validate inputs
    if not is_valid_city(city):
        raise HTTPException(status_code=400, detail="Invalid city selected")
    
    if not is_valid_category(category):
        raise HTTPException(status_code=400, detail="Invalid category selected")
    
    # Redirect to SEO-friendly URL
    return RedirectResponse(
        url=f"/top10/{city}/{category}",
        status_code=status.HTTP_303_SEE_OTHER
    )


@app.get("/top10/{city_slug}/{category_slug}", response_class=HTMLResponse)
async def top10_list(request: Request, city_slug: str, category_slug: str):
    """Display Top 10 restaurant list."""
    # Validate slugs
    if not is_valid_city(city_slug):
        context = get_base_context(request)
        context.update({
            "error_title": "City Not Found",
            "error_message": f"We don't have data for '{city_slug}' yet. Please select from available cities.",
            "show_home_button": True
        })
        return templates.TemplateResponse("error.html", context, status_code=404)
    
    if not is_valid_category(category_slug):
        context = get_base_context(request)
        context.update({
            "error_title": "Category Not Found", 
            "error_message": f"The category '{category_slug}' is not available. Please select from our categories.",
            "show_home_button": True
        })
        return templates.TemplateResponse("error.html", context, status_code=404)
    
    # Check cache first
    cache_key = f"{city_slug}_{category_slug}"
    cached_data = cache.get(cache_key)
    
    if cached_data:
        # Use cached data
        restaurants = [RestaurantData(**r) for r in cached_data['restaurants']]
        generated_at = datetime.fromisoformat(cached_data['generated_at'])
        from_cache = True
    else:
        # Generate new list
        result = await master_critic.generate_top_10_list(city_slug, category_slug)
        
        if not result.success:
            context = get_base_context(request)
            context.update({
                "error_title": "Generation Error",
                "error_message": result.error or "Unable to generate list at this time. Please try again later.",
                "show_home_button": True
            })
            return templates.TemplateResponse("error.html", context, status_code=503)
        
        # Cache the result
        cache_data = {
            'restaurants': [r.model_dump() for r in result.restaurants],
            'generated_at': result.generated_at.isoformat()
        }
        cache.set(cache_key, cache_data, ttl=settings.cache_ttl)
        
        restaurants = result.restaurants
        generated_at = result.generated_at
        from_cache = False
    
    # Get metadata
    city_name = get_city_name(city_slug)
    category_name = get_category_name(category_slug)
    
    # Build context
    context = get_base_context(request)
    context.update({
        "page_title": f"Top 10 {category_name} Restaurants in {city_name}",
        "meta_description": f"Discover the best {category_name.lower()} restaurants in {city_name}. Expert recommendations from ZipPicks Master Critic.",
        "city_slug": city_slug,
        "city_name": city_name,
        "category_slug": category_slug,
        "category_name": category_name,
        "restaurants": restaurants,
        "generated_at": generated_at,
        "from_cache": from_cache,
        "cache_timestamp": format_cache_timestamp(generated_at.timestamp()),
        "canonical_url": f"https://zippicks.com/top10/{city_slug}/{category_slug}",
        "og_image": f"https://zippicks.com/api/og-image/{city_slug}/{category_slug}"
    })
    
    return templates.TemplateResponse("top10.html", context)


@app.get("/restaurant/{zpid}", response_class=HTMLResponse)
async def restaurant_detail(request: Request, zpid: str):
    """Individual restaurant details page."""
    # Get restaurant details
    restaurant = await master_critic.get_restaurant_details(zpid)
    
    if not restaurant:
        context = get_base_context(request)
        context.update({
            "error_title": "Restaurant Not Found",
            "error_message": "This restaurant could not be found in our database.",
            "show_home_button": True
        })
        return templates.TemplateResponse("error.html", context, status_code=404)
    
    context = get_base_context(request)
    context.update({
        "page_title": f"{restaurant.name} - Restaurant Details",
        "meta_description": f"Learn more about {restaurant.name}. {restaurant.reasoning[:150]}...",
        "restaurant": restaurant,
        "canonical_url": f"https://zippicks.com/restaurant/{zpid}"
    })
    
    return templates.TemplateResponse("restaurant.html", context)


@app.get("/city/{city_slug}", response_class=HTMLResponse)
async def city_overview(request: Request, city_slug: str):
    """City dining scene overview."""
    if not is_valid_city(city_slug):
        context = get_base_context(request)
        context.update({
            "error_title": "City Not Found",
            "error_message": f"We don't have data for '{city_slug}' yet.",
            "show_home_button": True
        })
        return templates.TemplateResponse("error.html", context, status_code=404)
    
    # Get city analysis
    city_data = await master_critic.analyze_city_dining_scene(city_slug)
    city_info = get_city_by_slug(city_slug)
    
    context = get_base_context(request)
    context.update({
        "page_title": f"{city_info.name} Dining Guide",
        "meta_description": f"Explore the {city_info.name} dining scene. Discover neighborhoods, trending cuisines, and top restaurants.",
        "city": city_info,
        "city_data": city_data,
        "canonical_url": f"https://zippicks.com/city/{city_slug}"
    })
    
    return templates.TemplateResponse("city.html", context)


@app.get("/api/cities", response_class=JSONResponse)
async def api_cities():
    """API endpoint for city list."""
    return JSONResponse(content={"cities": get_all_cities()})


@app.get("/api/categories", response_class=JSONResponse)
async def api_categories():
    """API endpoint for category list."""
    return JSONResponse(content={"categories": get_all_categories()})


@app.get("/api/cache-stats", response_class=JSONResponse)
async def api_cache_stats():
    """API endpoint for cache statistics (debug only)."""
    if not settings.debug:
        raise HTTPException(status_code=403, detail="Not available in production")
    
    stats = cache.get_cache_size()
    return JSONResponse(content=stats)


@app.get("/health", response_class=JSONResponse)
async def health_check():
    """Health check endpoint."""
    return JSONResponse(content={
        "status": "healthy",
        "timestamp": datetime.now().isoformat(),
        "version": "1.0.0"
    })


# Error handlers
@app.exception_handler(404)
async def not_found_handler(request: Request, exc: HTTPException):
    """Handle 404 errors."""
    context = get_base_context(request)
    context.update({
        "error_title": "Page Not Found",
        "error_message": "The page you're looking for doesn't exist.",
        "show_home_button": True
    })
    return templates.TemplateResponse("error.html", context, status_code=404)


@app.exception_handler(500)
async def server_error_handler(request: Request, exc: Exception):
    """Handle 500 errors."""
    context = get_base_context(request)
    context.update({
        "error_title": "Server Error",
        "error_message": "Something went wrong. Please try again later.",
        "show_home_button": True
    })
    return templates.TemplateResponse("error.html", context, status_code=500)


# Startup event
@app.on_event("startup")
async def startup_event():
    """Run on application startup."""
    # Clean up expired cache entries
    expired = cache.cleanup_expired()
    if expired > 0:
        print(f"Cleaned up {expired} expired cache entries")
    
    print(f"{settings.app_name} started successfully!")


if __name__ == "__main__":
    import uvicorn
    uvicorn.run("main:app", host="0.0.0.0", port=8000, reload=True)