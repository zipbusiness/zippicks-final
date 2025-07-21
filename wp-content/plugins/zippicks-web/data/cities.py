"""City configuration and mappings for ZipPicks."""

from typing import Dict, List, Optional
from dataclasses import dataclass


@dataclass
class City:
    """City data structure with metadata."""
    slug: str
    name: str
    state: str
    zipcode_prefix: str
    timezone: str
    population_rank: int


# City slug to display name mapping
CITY_MAPPING: Dict[str, str] = {
    "los-angeles": "Los Angeles",
    "new-york": "New York",
    "chicago": "Chicago",
    "san-francisco": "San Francisco",
    "miami": "Miami",
    "austin": "Austin",
    "seattle": "Seattle",
    "boston": "Boston",
    "denver": "Denver",
    "atlanta": "Atlanta",
    "portland": "Portland",
    "philadelphia": "Philadelphia",
    "san-diego": "San Diego",
    "washington-dc": "Washington DC",
    "houston": "Houston",
    "dallas": "Dallas",
    "phoenix": "Phoenix",
    "las-vegas": "Las Vegas",
    "nashville": "Nashville",
    "new-orleans": "New Orleans"
}

# Detailed city information
CITIES: Dict[str, City] = {
    "los-angeles": City(
        slug="los-angeles",
        name="Los Angeles",
        state="CA",
        zipcode_prefix="900",
        timezone="America/Los_Angeles",
        population_rank=2
    ),
    "new-york": City(
        slug="new-york",
        name="New York",
        state="NY",
        zipcode_prefix="100",
        timezone="America/New_York",
        population_rank=1
    ),
    "chicago": City(
        slug="chicago",
        name="Chicago",
        state="IL",
        zipcode_prefix="606",
        timezone="America/Chicago",
        population_rank=3
    ),
    "san-francisco": City(
        slug="san-francisco",
        name="San Francisco",
        state="CA",
        zipcode_prefix="941",
        timezone="America/Los_Angeles",
        population_rank=17
    ),
    "miami": City(
        slug="miami",
        name="Miami",
        state="FL",
        zipcode_prefix="331",
        timezone="America/New_York",
        population_rank=42
    ),
    "austin": City(
        slug="austin",
        name="Austin",
        state="TX",
        zipcode_prefix="787",
        timezone="America/Chicago",
        population_rank=11
    ),
    "seattle": City(
        slug="seattle",
        name="Seattle",
        state="WA",
        zipcode_prefix="981",
        timezone="America/Los_Angeles",
        population_rank=18
    ),
    "boston": City(
        slug="boston",
        name="Boston",
        state="MA",
        zipcode_prefix="021",
        timezone="America/New_York",
        population_rank=24
    ),
    "denver": City(
        slug="denver",
        name="Denver",
        state="CO",
        zipcode_prefix="802",
        timezone="America/Denver",
        population_rank=19
    ),
    "atlanta": City(
        slug="atlanta",
        name="Atlanta",
        state="GA",
        zipcode_prefix="303",
        timezone="America/New_York",
        population_rank=38
    ),
    "portland": City(
        slug="portland",
        name="Portland",
        state="OR",
        zipcode_prefix="972",
        timezone="America/Los_Angeles",
        population_rank=26
    ),
    "philadelphia": City(
        slug="philadelphia",
        name="Philadelphia",
        state="PA",
        zipcode_prefix="191",
        timezone="America/New_York",
        population_rank=6
    ),
    "san-diego": City(
        slug="san-diego",
        name="San Diego",
        state="CA",
        zipcode_prefix="921",
        timezone="America/Los_Angeles",
        population_rank=8
    ),
    "washington-dc": City(
        slug="washington-dc",
        name="Washington DC",
        state="DC",
        zipcode_prefix="200",
        timezone="America/New_York",
        population_rank=20
    ),
    "houston": City(
        slug="houston",
        name="Houston",
        state="TX",
        zipcode_prefix="770",
        timezone="America/Chicago",
        population_rank=4
    ),
    "dallas": City(
        slug="dallas",
        name="Dallas",
        state="TX",
        zipcode_prefix="752",
        timezone="America/Chicago",
        population_rank=9
    ),
    "phoenix": City(
        slug="phoenix",
        name="Phoenix",
        state="AZ",
        zipcode_prefix="850",
        timezone="America/Phoenix",
        population_rank=5
    ),
    "las-vegas": City(
        slug="las-vegas",
        name="Las Vegas",
        state="NV",
        zipcode_prefix="891",
        timezone="America/Los_Angeles",
        population_rank=28
    ),
    "nashville": City(
        slug="nashville",
        name="Nashville",
        state="TN",
        zipcode_prefix="372",
        timezone="America/Chicago",
        population_rank=21
    ),
    "new-orleans": City(
        slug="new-orleans",
        name="New Orleans",
        state="LA",
        zipcode_prefix="701",
        timezone="America/Chicago",
        population_rank=51
    )
}


def get_city_by_slug(slug: str) -> Optional[City]:
    """Get city object by slug."""
    return CITIES.get(slug)


def get_city_name(slug: str) -> str:
    """Get city display name by slug."""
    return CITY_MAPPING.get(slug, slug.replace("-", " ").title())


def get_all_cities() -> List[Dict[str, str]]:
    """Get all cities as list of dicts for API responses."""
    return [
        {"slug": slug, "name": name}
        for slug, name in sorted(CITY_MAPPING.items(), key=lambda x: x[1])
    ]


def is_valid_city(slug: str) -> bool:
    """Check if city slug is valid."""
    return slug in CITY_MAPPING