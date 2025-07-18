#!/usr/bin/env python3
"""
Generate city-state mapping JSON file from city_list.py data.

This script creates a static JSON file that can be used by PHP for fast lookups.
The generated file contains normalized city names with state codes and coordinates.

Usage:
    python3 generate-city-state-map.py

The script expects the zipbusiness-api city_list.py to be available at:
    /Users/jeffsnewmacbook/Desktop/zipbusiness/zipbusiness-api/scripts/city_list.py

Output:
    ../includes/mastercritic/data/city-state-map.json
"""

import json
import os
import sys
from pathlib import Path


def find_city_list_module():
    """Find and import the city_list module from various possible locations."""
    # Primary location
    primary_path = Path('/Users/jeffsnewmacbook/Desktop/zipbusiness/zipbusiness-api/scripts')
    
    # Alternative locations to check
    alternative_paths = [
        Path.home() / 'Desktop' / 'zipbusiness' / 'zipbusiness-api' / 'scripts',
        Path.cwd().parent.parent.parent.parent / 'zipbusiness' / 'zipbusiness-api' / 'scripts',
        Path('/opt/zipbusiness/zipbusiness-api/scripts'),
    ]
    
    # Try primary path first
    if primary_path.exists():
        sys.path.insert(0, str(primary_path))
        try:
            from city_list import CITIES
            print(f"✓ Loaded city_list from: {primary_path}")
            return CITIES
        except ImportError:
            pass
    
    # Try alternative paths
    for alt_path in alternative_paths:
        if alt_path.exists():
            sys.path.insert(0, str(alt_path))
            try:
                from city_list import CITIES
                print(f"✓ Loaded city_list from: {alt_path}")
                return CITIES
            except ImportError:
                continue
    
    # If not found, provide helpful error message
    print("ERROR: Could not find city_list.py")
    print("\nSearched in the following locations:")
    print(f"  - {primary_path}")
    for path in alternative_paths:
        print(f"  - {path}")
    print("\nPlease ensure zipbusiness-api is available at one of these locations.")
    sys.exit(1)


def validate_city_data(cities):
    """Validate the structure of city data."""
    required_fields = ['slug', 'name', 'state', 'lat', 'lng']
    
    for idx, city in enumerate(cities):
        for field in required_fields:
            if field not in city:
                raise ValueError(f"City at index {idx} missing required field: {field}")
        
        # Validate data types
        if not isinstance(city['slug'], str) or not city['slug']:
            raise ValueError(f"Invalid slug for city at index {idx}")
        
        if not isinstance(city['name'], str) or not city['name']:
            raise ValueError(f"Invalid name for city at index {idx}")
        
        if not isinstance(city['state'], str) or len(city['state']) != 2:
            raise ValueError(f"Invalid state code for city {city['name']}")
        
        if not isinstance(city['lat'], (int, float)):
            raise ValueError(f"Invalid latitude for city {city['name']}")
        
        if not isinstance(city['lng'], (int, float)):
            raise ValueError(f"Invalid longitude for city {city['name']}")


def generate_city_state_map(cities):
    """Generate city to state mapping from CITIES data."""
    city_state_map = {}
    
    for city in cities:
        # Primary slug entry
        slug = city['slug']
        city_state_map[slug] = {
            'state': city['state'].upper(),
            'name': city['name'],
            'lat': round(float(city['lat']), 4),
            'lng': round(float(city['lng']), 4)
        }
        
        # Alternative formats for flexible lookup
        # Replace hyphens with spaces
        alt_slug = slug.replace('-', ' ')
        if alt_slug != slug:
            city_state_map[alt_slug] = city_state_map[slug]
        
        # Lowercase name version
        name_lower = city['name'].lower()
        if name_lower != slug and name_lower != alt_slug:
            city_state_map[name_lower] = city_state_map[slug]
    
    return city_state_map


def main():
    """Main function to generate the JSON file."""
    print("City-State Map Generator")
    print("=" * 50)
    
    # Import city data
    CITIES = find_city_list_module()
    
    # Validate data
    print("\nValidating city data...")
    try:
        validate_city_data(CITIES)
        print(f"✓ Validated {len(CITIES)} cities")
    except ValueError as e:
        print(f"✗ Validation error: {e}")
        sys.exit(1)
    
    # Generate the mapping
    print("\nGenerating city-state mapping...")
    city_state_map = generate_city_state_map(CITIES)
    
    # Determine output path (relative to plugin directory)
    script_dir = Path(__file__).parent
    plugin_dir = script_dir.parent
    data_dir = plugin_dir / 'includes' / 'mastercritic' / 'data'
    
    # Create data directory if it doesn't exist
    data_dir.mkdir(parents=True, exist_ok=True)
    
    output_file = data_dir / 'city-state-map.json'
    
    # Write the JSON file
    print(f"\nWriting output to: {output_file}")
    try:
        with open(output_file, 'w', encoding='utf-8') as f:
            json.dump(city_state_map, f, indent=2, sort_keys=True, ensure_ascii=False)
        
        # Verify the file was written correctly
        file_size = output_file.stat().st_size
        print(f"✓ Successfully wrote {file_size:,} bytes")
        
    except Exception as e:
        print(f"✗ Error writing file: {e}")
        sys.exit(1)
    
    # Summary statistics
    print("\nSummary:")
    print(f"  Total cities: {len(CITIES)}")
    print(f"  Total map entries: {len(city_state_map)} (includes alternative lookups)")
    
    # State distribution
    state_counts = {}
    for city in CITIES:
        state = city['state'].upper()
        state_counts[state] = state_counts.get(state, 0) + 1
    
    print(f"  States covered: {len(state_counts)}")
    print("\nTop 5 states by city count:")
    for state, count in sorted(state_counts.items(), key=lambda x: x[1], reverse=True)[:5]:
        print(f"    {state}: {count} cities")
    
    print("\n✓ City-state map generation complete!")


if __name__ == "__main__":
    main()