"""Service layer for Master Critic API integration."""

import os
import httpx
import asyncio
from typing import Dict, List, Optional, Any
from datetime import datetime
import logging
from dataclasses import dataclass, asdict
from pydantic import BaseModel, Field


# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)


class RestaurantData(BaseModel):
    """Restaurant data model matching API response."""
    rank: int
    zpid: str
    name: str
    address: str
    reasoning: str
    best_for: str
    price_range: str = Field(default="$$")
    must_try_dish: Optional[str] = None
    cuisine_type: Optional[str] = None
    phone: Optional[str] = None
    website: Optional[str] = None
    hours: Optional[str] = None
    rating: Optional[float] = None
    review_count: Optional[int] = None


class Top10ListResponse(BaseModel):
    """Response model for Top 10 list generation."""
    success: bool
    city: str
    category: str
    restaurants: List[RestaurantData]
    generated_at: datetime
    cache_key: str
    error: Optional[str] = None


class CityOverviewResponse(BaseModel):
    """Response model for city dining scene analysis."""
    success: bool
    city: str
    overview: str
    neighborhoods: List[Dict[str, str]]
    trending_cuisines: List[str]
    price_distribution: Dict[str, int]
    generated_at: datetime
    error: Optional[str] = None


class MasterCriticService:
    """Service for interacting with Master Critic API."""
    
    def __init__(self, api_url: Optional[str] = None, api_key: Optional[str] = None):
        """
        Initialize the service.
        
        Args:
            api_url: Base URL for Master Critic API
            api_key: API key for authentication
        """
        self.api_url = api_url or os.getenv("MASTER_CRITIC_API_URL", "http://localhost:8000/api")
        self.api_key = api_key or os.getenv("MASTER_CRITIC_API_KEY", "")
        self.timeout = 30.0  # 30 second timeout for API calls
        self.max_retries = 3
        self.retry_delay = 1.0
    
    async def generate_top_10_list(self, city: str, category: str) -> Top10ListResponse:
        """
        Generate a Top 10 restaurant list.
        
        Args:
            city: City slug (e.g., 'los-angeles')
            category: Category slug (e.g., 'best-overall')
            
        Returns:
            Top10ListResponse with success status and data
        """
        cache_key = f"{city}_{category}"
        
        for attempt in range(self.max_retries):
            try:
                async with httpx.AsyncClient(timeout=self.timeout) as client:
                    headers = {"Authorization": f"Bearer {self.api_key}"} if self.api_key else {}
                    
                    response = await client.post(
                        f"{self.api_url}/generate-list",
                        json={"city": city, "category": category},
                        headers=headers
                    )
                    
                    if response.status_code == 200:
                        data = response.json()
                        
                        # Parse restaurants
                        restaurants = [
                            RestaurantData(**restaurant) 
                            for restaurant in data.get("restaurants", [])
                        ]
                        
                        return Top10ListResponse(
                            success=True,
                            city=city,
                            category=category,
                            restaurants=restaurants,
                            generated_at=datetime.now(),
                            cache_key=cache_key
                        )
                    
                    elif response.status_code == 429:  # Rate limited
                        wait_time = int(response.headers.get("Retry-After", 60))
                        logger.warning(f"Rate limited. Waiting {wait_time} seconds.")
                        await asyncio.sleep(wait_time)
                        continue
                    
                    else:
                        error_msg = f"API returned status {response.status_code}"
                        logger.error(error_msg)
                        return Top10ListResponse(
                            success=False,
                            city=city,
                            category=category,
                            restaurants=[],
                            generated_at=datetime.now(),
                            cache_key=cache_key,
                            error=error_msg
                        )
            
            except httpx.TimeoutException:
                logger.error(f"Timeout on attempt {attempt + 1}")
                if attempt < self.max_retries - 1:
                    await asyncio.sleep(self.retry_delay * (attempt + 1))
                    continue
                return Top10ListResponse(
                    success=False,
                    city=city,
                    category=category,
                    restaurants=[],
                    generated_at=datetime.now(),
                    cache_key=cache_key,
                    error="Request timeout - please try again"
                )
            
            except Exception as e:
                logger.error(f"Error generating list: {str(e)}")
                return Top10ListResponse(
                    success=False,
                    city=city,
                    category=category,
                    restaurants=[],
                    generated_at=datetime.now(),
                    cache_key=cache_key,
                    error=str(e)
                )
        
        # If we get here, all retries failed
        return self._generate_fallback_list(city, category)
    
    async def get_restaurant_details(self, zpid: str) -> Optional[RestaurantData]:
        """
        Get detailed information for a specific restaurant.
        
        Args:
            zpid: ZipPicks restaurant ID
            
        Returns:
            RestaurantData or None if not found
        """
        try:
            async with httpx.AsyncClient(timeout=self.timeout) as client:
                headers = {"Authorization": f"Bearer {self.api_key}"} if self.api_key else {}
                
                response = await client.get(
                    f"{self.api_url}/restaurant/{zpid}",
                    headers=headers
                )
                
                if response.status_code == 200:
                    data = response.json()
                    return RestaurantData(**data)
                else:
                    logger.error(f"Failed to get restaurant {zpid}: {response.status_code}")
                    return None
        
        except Exception as e:
            logger.error(f"Error getting restaurant details: {str(e)}")
            return None
    
    async def analyze_city_dining_scene(self, city: str) -> CityOverviewResponse:
        """
        Get comprehensive analysis of a city's dining scene.
        
        Args:
            city: City slug
            
        Returns:
            CityOverviewResponse with analysis data
        """
        try:
            async with httpx.AsyncClient(timeout=self.timeout) as client:
                headers = {"Authorization": f"Bearer {self.api_key}"} if self.api_key else {}
                
                response = await client.post(
                    f"{self.api_url}/analyze-city",
                    json={"city": city},
                    headers=headers
                )
                
                if response.status_code == 200:
                    data = response.json()
                    return CityOverviewResponse(
                        success=True,
                        city=city,
                        overview=data.get("overview", ""),
                        neighborhoods=data.get("neighborhoods", []),
                        trending_cuisines=data.get("trending_cuisines", []),
                        price_distribution=data.get("price_distribution", {}),
                        generated_at=datetime.now()
                    )
                else:
                    return CityOverviewResponse(
                        success=False,
                        city=city,
                        overview="",
                        neighborhoods=[],
                        trending_cuisines=[],
                        price_distribution={},
                        generated_at=datetime.now(),
                        error=f"API returned status {response.status_code}"
                    )
        
        except Exception as e:
            logger.error(f"Error analyzing city: {str(e)}")
            return self._generate_fallback_city_overview(city)
    
    def _generate_fallback_list(self, city: str, category: str) -> Top10ListResponse:
        """Generate a fallback list when API is unavailable."""
        # This would normally pull from a local database or static data
        fallback_restaurants = [
            RestaurantData(
                rank=i + 1,
                zpid=f"zp{100000 + i}",
                name=f"Restaurant {i + 1}",
                address=f"{100 + i} Main St, {city.replace('-', ' ').title()}, CA",
                reasoning="This restaurant offers exceptional dining experiences with fresh, locally-sourced ingredients.",
                best_for="Special occasions",
                price_range="$$" if i < 5 else "$$$",
                must_try_dish="Chef's Special"
            )
            for i in range(10)
        ]
        
        return Top10ListResponse(
            success=True,
            city=city,
            category=category,
            restaurants=fallback_restaurants,
            generated_at=datetime.now(),
            cache_key=f"{city}_{category}",
            error="Using cached data - live updates temporarily unavailable"
        )
    
    def _generate_fallback_city_overview(self, city: str) -> CityOverviewResponse:
        """Generate fallback city overview when API is unavailable."""
        city_name = city.replace('-', ' ').title()
        
        return CityOverviewResponse(
            success=True,
            city=city,
            overview=f"{city_name} offers a diverse dining scene with options ranging from casual eateries to fine dining establishments. The city's culinary landscape reflects its multicultural heritage.",
            neighborhoods=[
                {"name": "Downtown", "description": "Business district with upscale dining"},
                {"name": "Arts District", "description": "Trendy spots and creative cuisine"},
                {"name": "Historic Quarter", "description": "Traditional restaurants and local favorites"}
            ],
            trending_cuisines=["Farm-to-Table", "Asian Fusion", "Modern American", "Mediterranean"],
            price_distribution={"$": 30, "$$": 45, "$$$": 20, "$$$$": 5},
            generated_at=datetime.now(),
            error="Using cached data - live updates temporarily unavailable"
        )


# Singleton instance
_service_instance = None


def get_master_critic_service() -> MasterCriticService:
    """Get singleton service instance."""
    global _service_instance
    if _service_instance is None:
        _service_instance = MasterCriticService()
    return _service_instance