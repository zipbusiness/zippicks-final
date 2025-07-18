"""Enterprise-grade caching utility for ZipPicks web application."""

import json
import os
import time
from datetime import datetime, timedelta
from pathlib import Path
from typing import Any, Dict, Optional, Union
import hashlib
import fcntl
from contextlib import contextmanager


class CacheManager:
    """Thread-safe file-based cache manager with expiration support."""
    
    def __init__(self, cache_dir: str = ".cache", default_ttl: int = 86400):
        """
        Initialize cache manager.
        
        Args:
            cache_dir: Directory for cache files
            default_ttl: Default time-to-live in seconds (24 hours)
        """
        self.cache_dir = Path(cache_dir)
        self.cache_dir.mkdir(exist_ok=True)
        self.default_ttl = default_ttl
        self._locks = {}
    
    def _get_cache_path(self, key: str) -> Path:
        """Generate cache file path from key."""
        # Create subdirectories based on key prefix for better file system performance
        key_hash = hashlib.md5(key.encode()).hexdigest()
        subdir = self.cache_dir / key_hash[:2]
        subdir.mkdir(exist_ok=True)
        return subdir / f"{key_hash}.json"
    
    @contextmanager
    def _file_lock(self, filepath: Path):
        """Context manager for file locking to ensure thread safety."""
        lockfile = filepath.with_suffix('.lock')
        lockfile.touch(exist_ok=True)
        
        with open(lockfile, 'w') as lock_fd:
            try:
                fcntl.flock(lock_fd.fileno(), fcntl.LOCK_EX)
                yield
            finally:
                fcntl.flock(lock_fd.fileno(), fcntl.LOCK_UN)
    
    def get(self, key: str) -> Optional[Any]:
        """
        Retrieve value from cache if it exists and hasn't expired.
        
        Args:
            key: Cache key
            
        Returns:
            Cached value or None if not found/expired
        """
        cache_path = self._get_cache_path(key)
        
        if not cache_path.exists():
            return None
        
        try:
            with self._file_lock(cache_path):
                with open(cache_path, 'r') as f:
                    cache_data = json.load(f)
                
                # Check expiration
                expires_at = cache_data.get('expires_at', 0)
                if time.time() > expires_at:
                    # Clean up expired cache
                    cache_path.unlink(missing_ok=True)
                    return None
                
                return cache_data.get('data')
        
        except (json.JSONDecodeError, OSError, IOError):
            # Handle corrupted cache files
            cache_path.unlink(missing_ok=True)
            return None
    
    def set(self, key: str, value: Any, ttl: Optional[int] = None) -> bool:
        """
        Store value in cache with expiration.
        
        Args:
            key: Cache key
            value: Value to cache (must be JSON serializable)
            ttl: Time-to-live in seconds (uses default if not specified)
            
        Returns:
            True if successfully cached, False otherwise
        """
        cache_path = self._get_cache_path(key)
        ttl = ttl or self.default_ttl
        
        cache_data = {
            'key': key,
            'data': value,
            'created_at': time.time(),
            'expires_at': time.time() + ttl,
            'ttl': ttl
        }
        
        try:
            with self._file_lock(cache_path):
                # Write to temporary file first for atomicity
                temp_path = cache_path.with_suffix('.tmp')
                with open(temp_path, 'w') as f:
                    json.dump(cache_data, f, indent=2)
                
                # Atomic move
                temp_path.replace(cache_path)
                return True
                
        except (OSError, IOError, TypeError):
            return False
    
    def delete(self, key: str) -> bool:
        """
        Remove item from cache.
        
        Args:
            key: Cache key
            
        Returns:
            True if deleted, False if not found
        """
        cache_path = self._get_cache_path(key)
        
        try:
            with self._file_lock(cache_path):
                cache_path.unlink()
                return True
        except FileNotFoundError:
            return False
    
    def clear(self) -> int:
        """
        Clear all cached items.
        
        Returns:
            Number of items cleared
        """
        count = 0
        for cache_file in self.cache_dir.rglob("*.json"):
            try:
                cache_file.unlink()
                count += 1
            except OSError:
                pass
        return count
    
    def cleanup_expired(self) -> int:
        """
        Remove all expired cache entries.
        
        Returns:
            Number of expired items removed
        """
        count = 0
        current_time = time.time()
        
        for cache_file in self.cache_dir.rglob("*.json"):
            try:
                with self._file_lock(cache_file):
                    with open(cache_file, 'r') as f:
                        cache_data = json.load(f)
                    
                    if current_time > cache_data.get('expires_at', 0):
                        cache_file.unlink()
                        count += 1
            except (json.JSONDecodeError, OSError, IOError):
                # Remove corrupted files
                cache_file.unlink(missing_ok=True)
                count += 1
        
        return count
    
    def get_metadata(self, key: str) -> Optional[Dict[str, Any]]:
        """
        Get cache metadata without retrieving the actual data.
        
        Args:
            key: Cache key
            
        Returns:
            Metadata dict with created_at, expires_at, ttl
        """
        cache_path = self._get_cache_path(key)
        
        if not cache_path.exists():
            return None
        
        try:
            with self._file_lock(cache_path):
                with open(cache_path, 'r') as f:
                    cache_data = json.load(f)
                
                return {
                    'created_at': cache_data.get('created_at'),
                    'expires_at': cache_data.get('expires_at'),
                    'ttl': cache_data.get('ttl'),
                    'is_expired': time.time() > cache_data.get('expires_at', 0)
                }
        except (json.JSONDecodeError, OSError, IOError):
            return None
    
    def get_cache_size(self) -> Dict[str, Union[int, float]]:
        """
        Get cache statistics.
        
        Returns:
            Dict with total_files, total_size_mb, expired_files
        """
        total_files = 0
        total_size = 0
        expired_files = 0
        current_time = time.time()
        
        for cache_file in self.cache_dir.rglob("*.json"):
            try:
                total_files += 1
                total_size += cache_file.stat().st_size
                
                with open(cache_file, 'r') as f:
                    cache_data = json.load(f)
                    if current_time > cache_data.get('expires_at', 0):
                        expired_files += 1
            except (OSError, IOError, json.JSONDecodeError):
                pass
        
        return {
            'total_files': total_files,
            'total_size_mb': round(total_size / (1024 * 1024), 2),
            'expired_files': expired_files
        }


# Singleton instance
_cache_instance = None


def get_cache() -> CacheManager:
    """Get singleton cache instance."""
    global _cache_instance
    if _cache_instance is None:
        _cache_instance = CacheManager()
    return _cache_instance


# Convenience functions
def cache_get(key: str) -> Optional[Any]:
    """Get value from cache."""
    return get_cache().get(key)


def cache_set(key: str, value: Any, ttl: Optional[int] = None) -> bool:
    """Set value in cache."""
    return get_cache().set(key, value, ttl)


def cache_delete(key: str) -> bool:
    """Delete value from cache."""
    return get_cache().delete(key)


def format_cache_timestamp(timestamp: float) -> str:
    """Format cache timestamp for display."""
    return datetime.fromtimestamp(timestamp).strftime("%Y-%m-%d %H:%M:%S")