"""Category configuration and mappings for ZipPicks."""

from typing import Dict, List, Optional
from dataclasses import dataclass


@dataclass
class Category:
    """Category data structure with metadata."""
    slug: str
    name: str
    description: str
    icon: str
    sort_order: int
    is_premium: bool = False


# Category slug to display name mapping
CATEGORY_MAPPING: Dict[str, str] = {
    "best-overall": "Best Overall",
    "best-value": "Best Value",
    "date-night": "Date Night",
    "business-lunch": "Business Lunch",
    "family-friendly": "Family Friendly",
    "food-truck": "Food Truck",
    "fine-dining": "Fine Dining",
    "brunch": "Brunch",
    "late-night": "Late Night",
    "outdoor-dining": "Outdoor Dining",
    "vegetarian": "Vegetarian",
    "seafood": "Seafood",
    "steakhouse": "Steakhouse",
    "pizza": "Pizza",
    "mexican": "Mexican",
    "italian": "Italian",
    "asian-fusion": "Asian Fusion",
    "sushi": "Sushi",
    "barbecue": "Barbecue",
    "mediterranean": "Mediterranean"
}

# Detailed category information
CATEGORIES: Dict[str, Category] = {
    "best-overall": Category(
        slug="best-overall",
        name="Best Overall",
        description="The absolute best restaurants across all criteria",
        icon="⭐",
        sort_order=1,
        is_premium=False
    ),
    "best-value": Category(
        slug="best-value",
        name="Best Value",
        description="Outstanding quality for the price",
        icon="💰",
        sort_order=2,
        is_premium=False
    ),
    "date-night": Category(
        slug="date-night",
        name="Date Night",
        description="Perfect for romantic evenings",
        icon="❤️",
        sort_order=3,
        is_premium=False
    ),
    "business-lunch": Category(
        slug="business-lunch",
        name="Business Lunch",
        description="Ideal for professional meetings",
        icon="💼",
        sort_order=4,
        is_premium=False
    ),
    "family-friendly": Category(
        slug="family-friendly",
        name="Family Friendly",
        description="Great for dining with kids",
        icon="👨‍👩‍👧‍👦",
        sort_order=5,
        is_premium=False
    ),
    "food-truck": Category(
        slug="food-truck",
        name="Food Truck",
        description="Best mobile dining options",
        icon="🚚",
        sort_order=6,
        is_premium=False
    ),
    "fine-dining": Category(
        slug="fine-dining",
        name="Fine Dining",
        description="Upscale culinary experiences",
        icon="🍽️",
        sort_order=7,
        is_premium=True
    ),
    "brunch": Category(
        slug="brunch",
        name="Brunch",
        description="Best weekend brunch spots",
        icon="🥞",
        sort_order=8,
        is_premium=False
    ),
    "late-night": Category(
        slug="late-night",
        name="Late Night",
        description="Open late for night owls",
        icon="🌙",
        sort_order=9,
        is_premium=False
    ),
    "outdoor-dining": Category(
        slug="outdoor-dining",
        name="Outdoor Dining",
        description="Al fresco dining experiences",
        icon="☀️",
        sort_order=10,
        is_premium=False
    ),
    "vegetarian": Category(
        slug="vegetarian",
        name="Vegetarian",
        description="Plant-based excellence",
        icon="🥗",
        sort_order=11,
        is_premium=False
    ),
    "seafood": Category(
        slug="seafood",
        name="Seafood",
        description="Fresh catches and ocean fare",
        icon="🦞",
        sort_order=12,
        is_premium=False
    ),
    "steakhouse": Category(
        slug="steakhouse",
        name="Steakhouse",
        description="Premium cuts and grills",
        icon="🥩",
        sort_order=13,
        is_premium=True
    ),
    "pizza": Category(
        slug="pizza",
        name="Pizza",
        description="From classic to artisanal pies",
        icon="🍕",
        sort_order=14,
        is_premium=False
    ),
    "mexican": Category(
        slug="mexican",
        name="Mexican",
        description="Authentic and modern Mexican cuisine",
        icon="🌮",
        sort_order=15,
        is_premium=False
    ),
    "italian": Category(
        slug="italian",
        name="Italian",
        description="From trattorias to fine Italian",
        icon="🍝",
        sort_order=16,
        is_premium=False
    ),
    "asian-fusion": Category(
        slug="asian-fusion",
        name="Asian Fusion",
        description="Creative East-meets-West cuisine",
        icon="🥢",
        sort_order=17,
        is_premium=False
    ),
    "sushi": Category(
        slug="sushi",
        name="Sushi",
        description="Fresh rolls and sashimi",
        icon="🍣",
        sort_order=18,
        is_premium=True
    ),
    "barbecue": Category(
        slug="barbecue",
        name="Barbecue",
        description="Smoked meats and BBQ classics",
        icon="🍖",
        sort_order=19,
        is_premium=False
    ),
    "mediterranean": Category(
        slug="mediterranean",
        name="Mediterranean",
        description="Flavors from the Mediterranean coast",
        icon="🫒",
        sort_order=20,
        is_premium=False
    )
}


def get_category_by_slug(slug: str) -> Optional[Category]:
    """Get category object by slug."""
    return CATEGORIES.get(slug)


def get_category_name(slug: str) -> str:
    """Get category display name by slug."""
    return CATEGORY_MAPPING.get(slug, slug.replace("-", " ").title())


def get_all_categories() -> List[Dict[str, str]]:
    """Get all categories as list of dicts for API responses."""
    return [
        {
            "slug": cat.slug, 
            "name": cat.name,
            "icon": cat.icon,
            "is_premium": cat.is_premium
        }
        for cat in sorted(CATEGORIES.values(), key=lambda x: x.sort_order)
    ]


def get_free_categories() -> List[Dict[str, str]]:
    """Get only free categories."""
    return [
        {
            "slug": cat.slug, 
            "name": cat.name,
            "icon": cat.icon
        }
        for cat in sorted(CATEGORIES.values(), key=lambda x: x.sort_order)
        if not cat.is_premium
    ]


def is_valid_category(slug: str) -> bool:
    """Check if category slug is valid."""
    return slug in CATEGORY_MAPPING