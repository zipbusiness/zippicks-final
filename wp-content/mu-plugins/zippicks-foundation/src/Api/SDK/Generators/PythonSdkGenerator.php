<?php
/**
 * ZipPicks Python SDK Generator
 * 
 * Generates enterprise-grade Python client libraries
 * Supports Python 3.8+, type hints, async/await, and modern Python practices
 *
 * @package ZipPicks\Foundation\Api\SDK\Generators
 */

namespace ZipPicks\Foundation\Api\SDK\Generators;

use ZipPicks\Foundation\Logging\EnterpriseLogger;

class PythonSdkGenerator
{
    /**
     * Generator version
     */
    const VERSION = '1.0.0';

    /**
     * Configuration
     *
     * @var array
     */
    protected array $config;

    /**
     * Logger instance
     *
     * @var EnterpriseLogger
     */
    protected EnterpriseLogger $logger;

    /**
     * Generated files
     *
     * @var array
     */
    protected array $files = [];

    /**
     * Create Python SDK generator
     *
     * @param array $config
     * @param EnterpriseLogger $logger
     */
    public function __construct(array $config, EnterpriseLogger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Generate Python SDK from OpenAPI specification
     *
     * @param array $openApiSpec
     * @param string $version
     * @param array $options
     * @return array
     */
    public function generate(array $openApiSpec, string $version, array $options = []): array
    {
        $this->logger->info('Generating Python SDK', [
            'version' => $version,
            'options' => $options
        ]);

        $this->files = [];

        try {
            // Generate main client module
            $this->generateClient($openApiSpec, $version);
            
            // Generate resource modules
            $this->generateResources($openApiSpec, $version);
            
            // Generate model classes
            $this->generateModels($openApiSpec, $version);
            
            // Generate exception classes
            $this->generateExceptions($openApiSpec, $version);
            
            // Generate utilities
            $this->generateUtilities($openApiSpec, $version);
            
            // Generate async client
            $this->generateAsyncClient($openApiSpec, $version);
            
            // Generate package files
            $this->generatePackageFiles($openApiSpec, $version);
            
            // Generate documentation
            $this->generateDocumentation($openApiSpec, $version);
            
            // Generate tests
            $this->generateTests($openApiSpec, $version);

            return [
                'success' => true,
                'language' => 'python',
                'version' => $version,
                'files' => $this->files,
                'package_name' => 'zippicks-sdk'
            ];

        } catch (\Exception $e) {
            $this->logger->error('Python SDK generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Generate main client module
     *
     * @param array $openApiSpec
     * @param string $version
     * @return void
     */
    protected function generateClient(array $openApiSpec, string $version): void
    {
        $resources = $this->extractResources($openApiSpec);

        $content = "\"\"\"
ZipPicks Python SDK Client
The Taste Layer of the Internet

Version: {$version}
\"\"\"

import requests
from typing import Dict, Any, Optional, Union
from urllib.parse import urljoin

from .exceptions import (
    ZipPicksError,
    ApiError,
    AuthenticationError,
    ValidationError,
    RateLimitError,
    NotFoundError,
    ServerError
)
from .models import *
from .utils import ResponseTransformer

{$this->generateResourceImports($resources)}


class ZipPicksClient:
    \"\"\"
    ZipPicks API Client
    
    The official Python SDK for the ZipPicks API.
    Provides access to all ZipPicks API endpoints with proper error handling
    and response transformation.
    \"\"\"
    
    def __init__(
        self,
        api_key: Optional[str] = None,
        base_url: str = \"{$this->config['api_base_url']}\",
        timeout: int = 30,
        debug: bool = False,
        **kwargs
    ) -> None:
        \"\"\"
        Initialize ZipPicks client
        
        Args:
            api_key: Your ZipPicks API key
            base_url: API base URL
            timeout: Request timeout in seconds
            debug: Enable debug mode
            **kwargs: Additional configuration options
        \"\"\"
        self.config = {
            'api_key': api_key,
            'base_url': base_url.rstrip('/'),
            'timeout': timeout,
            'debug': debug,
            'user_agent': f'ZipPicks-Python-SDK/{$this->config['sdk_version']}',
            **kwargs
        }
        
        # Create requests session
        self.session = requests.Session()
        self.session.headers.update({
            'User-Agent': self.config['user_agent'],
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        })
        
        if self.config['api_key']:
            self.session.headers['X-API-Key'] = self.config['api_key']
        
        # Initialize response transformer
        self.transformer = ResponseTransformer()
        
        # Initialize resources
{$this->generateResourceInitialization($resources)}
    
    def request(
        self,
        method: str,
        path: str,
        params: Optional[Dict[str, Any]] = None,
        data: Optional[Dict[str, Any]] = None,
        **kwargs
    ) -> Dict[str, Any]:
        \"\"\"
        Make HTTP request to ZipPicks API
        
        Args:
            method: HTTP method (GET, POST, PUT, DELETE, etc.)
            path: API endpoint path
            params: Query parameters
            data: Request body data
            **kwargs: Additional request options
            
        Returns:
            API response data
            
        Raises:
            ZipPicksError: For various API errors
        \"\"\"
        url = urljoin(self.config['base_url'], path.lstrip('/'))
        
        try:
            response = self.session.request(
                method=method.upper(),
                url=url,
                params=params,
                json=data,
                timeout=self.config['timeout'],
                **kwargs
            )
            
            # Handle HTTP errors
            if response.status_code >= 400:
                self._handle_error_response(response)
            
            # Parse and transform response
            return self.transformer.transform(response)
            
        except requests.exceptions.Timeout:
            raise ZipPicksError('Request timeout')
        except requests.exceptions.ConnectionError:
            raise ZipPicksError('Connection error')
        except requests.exceptions.RequestException as e:
            raise ZipPicksError(f'Request failed: {str(e)}')
    
    def _handle_error_response(self, response: requests.Response) -> None:
        \"\"\"Handle error responses from the API\"\"\"
        try:
            error_data = response.json()
            error_info = error_data.get('error', {})
            message = error_info.get('message', 'Unknown API error')
            code = error_info.get('code', response.status_code)
        except ValueError:
            message = response.text or f'HTTP {response.status_code} error'
            code = response.status_code
        
        if response.status_code == 401:
            raise AuthenticationError(message, code)
        elif response.status_code == 404:
            raise NotFoundError(message, code)
        elif response.status_code == 422:
            raise ValidationError(message, code, error_data.get('errors'))
        elif response.status_code == 429:
            retry_after = response.headers.get('Retry-After')
            raise RateLimitError(message, code, retry_after)
        elif response.status_code >= 500:
            raise ServerError(message, code)
        else:
            raise ApiError(message, code)
    
    def set_api_key(self, api_key: str) -> None:
        \"\"\"Set API key for authentication\"\"\"
        self.config['api_key'] = api_key
        self.session.headers['X-API-Key'] = api_key
    
    def get_config(self) -> Dict[str, Any]:
        \"\"\"Get current configuration\"\"\"
        return self.config.copy()
    
    def close(self) -> None:
        \"\"\"Close the underlying session\"\"\"
        self.session.close()
    
    def __enter__(self):
        \"\"\"Context manager entry\"\"\"
        return self
    
    def __exit__(self, exc_type, exc_val, exc_tb):
        \"\"\"Context manager exit\"\"\"
        self.close()
";

        $this->files['zippicks_sdk/client.py'] = $content;
    }

    /**
     * Generate resource modules
     *
     * @param array $openApiSpec
     * @param string $version
     * @return void
     */
    protected function generateResources(array $openApiSpec, string $version): void
    {
        $resources = $this->extractResources($openApiSpec);

        foreach ($resources as $resource) {
            $resourceName = $this->snake_case($resource['name']);
            $className = $this->pascalCase($resource['name']);
            
            $content = $this->generateResourceModule($resource);
            $this->files["zippicks_sdk/resources/{$resourceName}.py"] = $content;
        }

        // Generate resources __init__.py
        $this->generateResourcesInit($resources);
    }

    /**
     * Generate models
     *
     * @param array $openApiSpec
     * @param string $version
     * @return void
     */
    protected function generateModels(array $openApiSpec, string $version): void
    {
        $schemas = $openApiSpec['components']['schemas'] ?? [];

        foreach ($schemas as $schemaName => $schema) {
            if ($this->isModelSchema($schema)) {
                $modelName = $this->snake_case($schemaName);
                $content = $this->generateModelClass($schemaName, $schema);
                $this->files["zippicks_sdk/models/{$modelName}.py"] = $content;
            }
        }

        // Generate models __init__.py
        $this->generateModelsInit($schemas);
    }

    /**
     * Generate exceptions
     *
     * @param array $openApiSpec
     * @param string $version
     * @return void
     */
    protected function generateExceptions(array $openApiSpec, string $version): void
    {
        $content = "\"\"\"
ZipPicks SDK Exceptions

Custom exception classes for handling different types of API errors.
\"\"\"

from typing import Optional, Dict, Any, Union


class ZipPicksError(Exception):
    \"\"\"Base exception class for ZipPicks SDK\"\"\"
    
    def __init__(self, message: str, code: Optional[int] = None) -> None:
        super().__init__(message)
        self.message = message
        self.code = code


class ApiError(ZipPicksError):
    \"\"\"General API error\"\"\"
    
    def __init__(
        self,
        message: str,
        code: Optional[int] = None,
        response_data: Optional[Dict[str, Any]] = None
    ) -> None:
        super().__init__(message, code)
        self.response_data = response_data or {}


class AuthenticationError(ApiError):
    \"\"\"Authentication failed error\"\"\"
    pass


class ValidationError(ApiError):
    \"\"\"Request validation error\"\"\"
    
    def __init__(
        self,
        message: str,
        code: Optional[int] = None,
        errors: Optional[Dict[str, Any]] = None
    ) -> None:
        super().__init__(message, code)
        self.errors = errors or {}


class RateLimitError(ApiError):
    \"\"\"Rate limit exceeded error\"\"\"
    
    def __init__(
        self,
        message: str,
        code: Optional[int] = None,
        retry_after: Optional[Union[int, str]] = None
    ) -> None:
        super().__init__(message, code)
        self.retry_after = retry_after


class NotFoundError(ApiError):
    \"\"\"Resource not found error\"\"\"
    pass


class ServerError(ApiError):
    \"\"\"Internal server error\"\"\"
    pass
";

        $this->files['zippicks_sdk/exceptions.py'] = $content;
    }

    /**
     * Generate utilities
     *
     * @param array $openApiSpec
     * @param string $version
     * @return void
     */
    protected function generateUtilities(array $openApiSpec, string $version): void
    {
        // Response transformer
        $transformerContent = "\"\"\"
Response transformation utilities
\"\"\"

import json
from typing import Dict, Any, Optional
from datetime import datetime
import requests


class ResponseTransformer:
    \"\"\"Transform API responses into structured data\"\"\"
    
    def transform(self, response: requests.Response) -> Dict[str, Any]:
        \"\"\"
        Transform HTTP response into structured data
        
        Args:
            response: HTTP response object
            
        Returns:
            Transformed response data
        \"\"\"
        try:
            data = response.json()
        except ValueError:
            data = {'raw': response.text}
        
        # Add response metadata
        data['_meta'] = {
            'status_code': response.status_code,
            'headers': dict(response.headers),
            'url': str(response.url)
        }
        
        return data
    
    def transform_datetime(self, value: str) -> Optional[datetime]:
        \"\"\"Transform ISO datetime string to datetime object\"\"\"
        if not value:
            return None
        
        try:
            return datetime.fromisoformat(value.replace('Z', '+00:00'))
        except ValueError:
            return None
";

        $this->files['zippicks_sdk/utils.py'] = $transformerContent;
    }

    /**
     * Generate async client
     *
     * @param array $openApiSpec
     * @param string $version
     * @return void
     */
    protected function generateAsyncClient(array $openApiSpec, string $version): void
    {
        $resources = $this->extractResources($openApiSpec);

        $content = "\"\"\"
ZipPicks Async Python SDK Client
For high-performance async/await operations
\"\"\"

import asyncio
import aiohttp
from typing import Dict, Any, Optional, Union
from urllib.parse import urljoin

from .exceptions import (
    ZipPicksError,
    ApiError,
    AuthenticationError,
    ValidationError,
    RateLimitError,
    NotFoundError,
    ServerError
)


class AsyncZipPicksClient:
    \"\"\"Async ZipPicks API Client\"\"\"
    
    def __init__(
        self,
        api_key: Optional[str] = None,
        base_url: str = \"{$this->config['api_base_url']}\",
        timeout: int = 30,
        debug: bool = False,
        **kwargs
    ) -> None:
        \"\"\"Initialize async ZipPicks client\"\"\"
        self.config = {
            'api_key': api_key,
            'base_url': base_url.rstrip('/'),
            'timeout': timeout,
            'debug': debug,
            'user_agent': f'ZipPicks-Python-AsyncSDK/{$this->config['sdk_version']}',
            **kwargs
        }
        
        self.session: Optional[aiohttp.ClientSession] = None
    
    async def __aenter__(self):
        \"\"\"Async context manager entry\"\"\"
        await self._ensure_session()
        return self
    
    async def __aexit__(self, exc_type, exc_val, exc_tb):
        \"\"\"Async context manager exit\"\"\"
        await self.close()
    
    async def _ensure_session(self) -> None:
        \"\"\"Ensure aiohttp session exists\"\"\"
        if self.session is None:
            headers = {
                'User-Agent': self.config['user_agent'],
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
            
            if self.config['api_key']:
                headers['X-API-Key'] = self.config['api_key']
            
            timeout = aiohttp.ClientTimeout(total=self.config['timeout'])
            self.session = aiohttp.ClientSession(
                headers=headers,
                timeout=timeout
            )
    
    async def request(
        self,
        method: str,
        path: str,
        params: Optional[Dict[str, Any]] = None,
        data: Optional[Dict[str, Any]] = None,
        **kwargs
    ) -> Dict[str, Any]:
        \"\"\"Make async HTTP request\"\"\"
        await self._ensure_session()
        
        url = urljoin(self.config['base_url'], path.lstrip('/'))
        
        try:
            async with self.session.request(
                method=method.upper(),
                url=url,
                params=params,
                json=data,
                **kwargs
            ) as response:
                
                # Handle errors
                if response.status >= 400:
                    await self._handle_error_response(response)
                
                # Parse response
                response_data = await response.json()
                return response_data
                
        except asyncio.TimeoutError:
            raise ZipPicksError('Request timeout')
        except aiohttp.ClientError as e:
            raise ZipPicksError(f'Request failed: {str(e)}')
    
    async def _handle_error_response(self, response: aiohttp.ClientResponse) -> None:
        \"\"\"Handle async error responses\"\"\"
        try:
            error_data = await response.json()
            error_info = error_data.get('error', {})
            message = error_info.get('message', 'Unknown API error')
            code = error_info.get('code', response.status)
        except:
            message = await response.text() or f'HTTP {response.status} error'
            code = response.status
        
        if response.status == 401:
            raise AuthenticationError(message, code)
        elif response.status == 404:
            raise NotFoundError(message, code)
        elif response.status == 422:
            raise ValidationError(message, code)
        elif response.status == 429:
            retry_after = response.headers.get('Retry-After')
            raise RateLimitError(message, code, retry_after)
        elif response.status >= 500:
            raise ServerError(message, code)
        else:
            raise ApiError(message, code)
    
    async def close(self) -> None:
        \"\"\"Close the session\"\"\"
        if self.session:
            await self.session.close()
            self.session = None
";

        $this->files['zippicks_sdk/async_client.py'] = $content;
    }

    /**
     * Generate package files
     *
     * @param array $openApiSpec
     * @param string $version
     * @return void
     */
    protected function generatePackageFiles(array $openApiSpec, string $version): void
    {
        // __init__.py
        $initContent = "\"\"\"
ZipPicks Python SDK
The Taste Layer of the Internet

Official Python SDK for the ZipPicks API
\"\"\"

__version__ = \"{$this->config['sdk_version']}\"
__author__ = \"ZipPicks\"
__email__ = \"{$this->config['contact_email']}\"

from .client import ZipPicksClient
from .async_client import AsyncZipPicksClient
from .exceptions import *
from .models import *

__all__ = [
    'ZipPicksClient',
    'AsyncZipPicksClient',
    'ZipPicksError',
    'ApiError',
    'AuthenticationError',
    'ValidationError',
    'RateLimitError',
    'NotFoundError',
    'ServerError'
]
";

        $this->files['zippicks_sdk/__init__.py'] = $initContent;

        // setup.py
        $setupContent = "\"\"\"
ZipPicks Python SDK Setup
\"\"\"

from setuptools import setup, find_packages

with open('README.md', 'r', encoding='utf-8') as fh:
    long_description = fh.read()

with open('requirements.txt', 'r', encoding='utf-8') as fh:
    requirements = [line.strip() for line in fh if line.strip() and not line.startswith('#')]

setup(
    name='zippicks-sdk',
    version='{$this->config['sdk_version']}',
    author='ZipPicks',
    author_email='{$this->config['contact_email']}',
    description='Official Python SDK for the ZipPicks API - The Taste Layer of the Internet',
    long_description=long_description,
    long_description_content_type='text/markdown',
    url='https://github.com/zippicks/python-sdk',
    project_urls={
        'Documentation': 'https://developers.zippicks.com',
        'Bug Reports': 'https://github.com/zippicks/python-sdk/issues',
        'Source': 'https://github.com/zippicks/python-sdk',
    },
    packages=find_packages(),
    classifiers=[
        'Development Status :: 5 - Production/Stable',
        'Intended Audience :: Developers',
        'License :: OSI Approved :: MIT License',
        'Operating System :: OS Independent',
        'Programming Language :: Python :: 3',
        'Programming Language :: Python :: 3.8',
        'Programming Language :: Python :: 3.9',
        'Programming Language :: Python :: 3.10',
        'Programming Language :: Python :: 3.11',
        'Programming Language :: Python :: 3.12',
        'Topic :: Internet :: WWW/HTTP',
        'Topic :: Software Development :: Libraries :: Python Modules',
    ],
    python_requires='>=3.8',
    install_requires=requirements,
    extras_require={
        'async': ['aiohttp>=3.8.0'],
        'dev': [
            'pytest>=7.0.0',
            'pytest-asyncio>=0.20.0',
            'pytest-cov>=4.0.0',
            'black>=22.0.0',
            'isort>=5.0.0',
            'flake8>=5.0.0',
            'mypy>=1.0.0',
        ],
    },
    keywords='zippicks api sdk local discovery taste graph',
    include_package_data=True,
    zip_safe=False,
)
";

        $this->files['setup.py'] = $setupContent;

        // requirements.txt
        $requirementsContent = "requests>=2.28.0
urllib3>=1.26.0
";

        $this->files['requirements.txt'] = $requirementsContent;

        // pyproject.toml
        $pyprojectContent = "[build-system]
requires = [\"setuptools>=45\", \"wheel\"]
build-backend = \"setuptools.build_meta\"

[project]
name = \"zippicks-sdk\"
version = \"{$this->config['sdk_version']}\"
description = \"Official Python SDK for the ZipPicks API\"
authors = [{name = \"ZipPicks\", email = \"{$this->config['contact_email']}\"}]
license = {text = \"MIT\"}
readme = \"README.md\"
requires-python = \">=3.8\"
classifiers = [
    \"Development Status :: 5 - Production/Stable\",
    \"Intended Audience :: Developers\",
    \"License :: OSI Approved :: MIT License\",
    \"Programming Language :: Python :: 3\",
]
dependencies = [
    \"requests>=2.28.0\",
    \"urllib3>=1.26.0\",
]

[project.optional-dependencies]
async = [\"aiohttp>=3.8.0\"]
dev = [
    \"pytest>=7.0.0\",
    \"pytest-asyncio>=0.20.0\",
    \"pytest-cov>=4.0.0\",
    \"black>=22.0.0\",
    \"isort>=5.0.0\",
    \"flake8>=5.0.0\",
    \"mypy>=1.0.0\",
]

[project.urls]
Homepage = \"https://developers.zippicks.com\"
Repository = \"https://github.com/zippicks/python-sdk\"
Documentation = \"https://developers.zippicks.com\"
\"Bug Reports\" = \"https://github.com/zippicks/python-sdk/issues\"

[tool.black]
line-length = 88
target-version = ['py38']

[tool.isort]
profile = \"black\"
line_length = 88

[tool.mypy]
python_version = \"3.8\"
warn_return_any = true
warn_unused_configs = true
";

        $this->files['pyproject.toml'] = $pyprojectContent;
    }

    /**
     * Generate documentation
     *
     * @param array $openApiSpec
     * @param string $version
     * @return void
     */
    protected function generateDocumentation(array $openApiSpec, string $version): void
    {
        $readmeContent = "# ZipPicks Python SDK

The official Python SDK for the ZipPicks API - The Taste Layer of the Internet.

## Installation

Install the SDK using pip:

```bash
pip install zippicks-sdk
```

For async support:

```bash
pip install zippicks-sdk[async]
```

## Quick Start

### Synchronous Client

```python
from zippicks_sdk import ZipPicksClient

# Initialize the client
client = ZipPicksClient(api_key='your-api-key-here')

# Search for businesses
businesses = client.businesses.list(zip='10001', vibes=['trendy', 'romantic'])

# Get business details
business = client.businesses.get('123')

# Create a review
review = client.reviews.create({
    'business_id': '123',
    'rating': 8.5,
    'content': 'Amazing vibe and great food!'
})
```

### Async Client

```python
import asyncio
from zippicks_sdk import AsyncZipPicksClient

async def main():
    async with AsyncZipPicksClient(api_key='your-api-key-here') as client:
        # Search for businesses
        businesses = await client.businesses.list(zip='10001')
        
        # Get business details
        business = await client.businesses.get('123')
        
        print(f'Found {len(businesses[\"data\"])} businesses')

# Run async code
asyncio.run(main())
```

### Context Manager

```python
# Automatic cleanup with context manager
with ZipPicksClient(api_key='your-api-key-here') as client:
    businesses = client.businesses.list()
    # Session automatically closed when exiting context
```

## Configuration

The SDK accepts the following configuration options:

- `api_key` (str): Your ZipPicks API key
- `base_url` (str): API base URL (default: `{$this->config['api_base_url']}`)
- `timeout` (int): Request timeout in seconds (default: 30)
- `debug` (bool): Enable debug mode (default: False)

```python
client = ZipPicksClient(
    api_key='your-api-key',
    base_url='https://api.zippicks.com',
    timeout=60,
    debug=True
)
```

## Resources

### Businesses

```python
# List businesses
businesses = client.businesses.list(
    zip='10001',
    vibes=['trendy', 'romantic'],
    page=1,
    per_page=20
)

# Get business
business = client.businesses.get('business-id')

# Create business
business = client.businesses.create({
    'name': 'Amazing Restaurant',
    'address': '123 Main St',
    'city': 'New York',
    'state': 'NY',
    'zip': '10001',
    'vibes': ['trendy', 'romantic']
})

# Update business
business = client.businesses.update('business-id', {
    'name': 'Updated Restaurant Name'
})

# Delete business
client.businesses.delete('business-id')
```

### Reviews

```python
# List reviews
reviews = client.reviews.list(business_id='123')

# Get review
review = client.reviews.get('review-id')

# Create review
review = client.reviews.create({
    'business_id': '123',
    'rating': 8.5,
    'content': 'Great experience!',
    'pillars': {
        'food_quality': 9.0,
        'service': 8.0,
        'atmosphere': 8.5
    }
})
```

### Vibes

```python
# List all vibes
vibes = client.vibes.list()

# Get vibe details
vibe = client.vibes.get('vibe-id')

# Search vibes
vibes = client.vibes.search(query='romantic')
```

### Search

```python
# Search across all content
results = client.search.query(
    q='best pizza',
    zip='10001',
    vibes=['casual', 'family-friendly']
)
```

## Error Handling

The SDK provides specific exception classes for different error types:

```python
from zippicks_sdk import (
    ZipPicksError,
    ApiError,
    AuthenticationError,
    ValidationError,
    RateLimitError,
    NotFoundError,
    ServerError
)

try:
    business = client.businesses.get('invalid-id')
except AuthenticationError:
    print('Invalid API key')
except NotFoundError:
    print('Business not found')
except ValidationError as e:
    print(f'Validation errors: {e.errors}')
except RateLimitError as e:
    print(f'Rate limited. Retry after: {e.retry_after}')
except ApiError as e:
    print(f'API error: {e.message} (Code: {e.code})')
except ZipPicksError as e:
    print(f'SDK error: {e.message}')
```

## Type Hints

The SDK includes full type hints for better IDE support:

```python
from typing import Dict, List, Optional
from zippicks_sdk import ZipPicksClient
from zippicks_sdk.models import Business, Review

client: ZipPicksClient = ZipPicksClient(api_key='your-key')

# Type-safe operations
businesses: Dict[str, List[Business]] = client.businesses.list()
business: Business = client.businesses.get('123')
review: Review = client.reviews.create(review_data)
```

## Async/Await Support

For high-performance applications, use the async client:

```python
import asyncio
from zippicks_sdk import AsyncZipPicksClient

async def fetch_multiple_businesses(business_ids):
    async with AsyncZipPicksClient(api_key='your-key') as client:
        tasks = [client.businesses.get(bid) for bid in business_ids]
        businesses = await asyncio.gather(*tasks)
        return businesses

# Fetch multiple businesses concurrently
business_ids = ['123', '456', '789']
businesses = asyncio.run(fetch_multiple_businesses(business_ids))
```

## Development

### Running Tests

```bash
# Install development dependencies
pip install -e .[dev]

# Run tests
pytest

# Run tests with coverage
pytest --cov=zippicks_sdk

# Run async tests
pytest -m asyncio
```

### Code Quality

```bash
# Format code
black zippicks_sdk/
isort zippicks_sdk/

# Lint code
flake8 zippicks_sdk/

# Type checking
mypy zippicks_sdk/
```

## Requirements

- Python 3.8 or higher
- requests library for HTTP client
- aiohttp library for async client (optional)
- Valid ZipPicks API key

## Support

- Documentation: https://developers.zippicks.com
- Support: {$this->config['contact_email']}
- Issues: https://github.com/zippicks/python-sdk/issues

## License

This SDK is released under the MIT License.
";

        $this->files['README.md'] = $readmeContent;
    }

    /**
     * Generate tests
     *
     * @param array $openApiSpec
     * @param string $version
     * @return void
     */
    protected function generateTests(array $openApiSpec, string $version): void
    {
        // Test client
        $testClientContent = "\"\"\"
Tests for ZipPicks Python SDK Client
\"\"\"

import pytest
import responses
from zippicks_sdk import ZipPicksClient
from zippicks_sdk.exceptions import AuthenticationError, ApiError


class TestZipPicksClient:
    \"\"\"Test ZipPicks client functionality\"\"\"
    
    def setup_method(self):
        \"\"\"Setup test client\"\"\"
        self.client = ZipPicksClient(
            api_key='test-key',
            base_url='https://api.test.zippicks.com'
        )
    
    def test_client_initialization(self):
        \"\"\"Test client initialization\"\"\"
        assert self.client.config['api_key'] == 'test-key'
        assert self.client.config['base_url'] == 'https://api.test.zippicks.com'
        assert 'X-API-Key' in self.client.session.headers
    
    @responses.activate
    def test_successful_request(self):
        \"\"\"Test successful API request\"\"\"
        responses.add(
            responses.GET,
            'https://api.test.zippicks.com/businesses',
            json={'success': True, 'data': []},
            status=200
        )
        
        result = self.client.request('GET', '/businesses')
        assert result['success'] is True
        assert 'data' in result
    
    @responses.activate
    def test_authentication_error(self):
        \"\"\"Test authentication error handling\"\"\"
        responses.add(
            responses.GET,
            'https://api.test.zippicks.com/businesses',
            json={'error': {'message': 'Invalid API key'}},
            status=401
        )
        
        with pytest.raises(AuthenticationError):
            self.client.request('GET', '/businesses')
    
    def test_context_manager(self):
        \"\"\"Test context manager usage\"\"\"
        with ZipPicksClient(api_key='test') as client:
            assert client.session is not None
        # Session should be closed after context exit
";

        $this->files['tests/test_client.py'] = $testClientContent;

        // Test configuration files
        $this->files['tests/__init__.py'] = '';
        $this->files['tests/conftest.py'] = "import pytest\nfrom zippicks_sdk import ZipPicksClient\n\n@pytest.fixture\ndef client():\n    return ZipPicksClient(api_key='test-key')\n";
    }

    /**
     * Helper methods for code generation
     */
    protected function generateResourceModule(array $resource): string
    {
        $className = $this->pascalCase($resource['name']);
        $methods = '';

        foreach ($resource['methods'] as $method) {
            $methods .= $this->generateResourceMethod($method) . "\n\n";
        }

        return "\"\"\"
{$className} Resource Module
\"\"\"

from typing import Dict, Any, Optional, List
from ..exceptions import ZipPicksError


class {$className}:
    \"\"\"Handle {$resource['name']}-related API operations\"\"\"
    
    def __init__(self, client) -> None:
        \"\"\"Initialize {$className} resource\"\"\"
        self.client = client

{$methods}";
    }

    protected function generateResourceMethod(array $method): string
    {
        $methodName = $this->snake_case($method['name']);
        $params = $this->generatePythonMethodParameters($method);
        $docstring = $this->generatePythonMethodDocstring($method);
        $pathParams = $this->extractPathParameters($method['path']);
        $httpMethod = strtoupper($method['method']);

        $bodyParam = '';
        if (in_array($httpMethod, ['POST', 'PUT', 'PATCH'])) {
            $bodyParam = ', data=data';
        }

        $queryParam = '';
        if ($httpMethod === 'GET') {
            $queryParam = ', params=query';
        }

        return "    def {$methodName}({$params}) -> Dict[str, Any]:
        {$docstring}
        path = f\"{$method['path']}\"" . ($pathParams ? $this->generatePythonPathFormatting($pathParams) : '') . "
        return self.client.request('{$httpMethod}', path{$bodyParam}{$queryParam})";
    }

    protected function generatePythonMethodParameters(array $method): string
    {
        $params = ['self'];
        
        // Add path parameters
        $pathParams = $this->extractPathParameters($method['path']);
        foreach ($pathParams as $param) {
            $params[] = "{$param}: str";
        }
        
        // Add data parameter for POST/PUT/PATCH
        if (in_array(strtoupper($method['method']), ['POST', 'PUT', 'PATCH'])) {
            $params[] = 'data: Dict[str, Any]';
        }
        
        // Add query parameter for GET
        if (strtoupper($method['method']) === 'GET') {
            $params[] = 'query: Optional[Dict[str, Any]] = None';
        }
        
        return implode(', ', $params);
    }

    protected function generatePythonMethodDocstring(array $method): string
    {
        $summary = $method['summary'] ?: $method['name'];
        $description = $method['description'] ? "\n        \n        {$method['description']}" : '';
        
        return "\"\"\"{$summary}{$description}
        
        Returns:
            API response data
        \"\"\"";
    }

    protected function generatePythonPathFormatting(array $pathParams): string
    {
        $formatting = '';
        foreach ($pathParams as $param) {
            $formatting .= ".replace('{{$param}}', {$param})";
        }
        return $formatting;
    }

    protected function generateModelClass(string $name, array $schema): string
    {
        $className = $this->pascalCase($name);
        $properties = $schema['properties'] ?? [];
        
        $attributes = '';
        $initParams = '';
        $initAssignments = '';

        foreach ($properties as $propName => $propSchema) {
            $pythonType = $this->getPythonType($propSchema);
            $optional = in_array($propName, $schema['required'] ?? []) ? '' : 'Optional[';
            $optionalClose = $optional ? ']' : '';
            
            $attributes .= "    {$propName}: {$optional}{$pythonType}{$optionalClose}\n";
            $initParams .= "        {$propName}: {$optional}{$pythonType}{$optionalClose} = None,\n";
            $initAssignments .= "        self.{$propName} = {$propName}\n";
        }

        return "\"\"\"
{$className} Model
\"\"\"

from typing import Optional, Dict, Any
from dataclasses import dataclass


@dataclass
class {$className}:
    \"\"\"
    {$className} data model
    \"\"\"
{$attributes}
    
    def __init__(
        self,
{$initParams}    ) -> None:
        \"\"\"Initialize {$className} instance\"\"\"
{$initAssignments}
    
    @classmethod
    def from_dict(cls, data: Dict[str, Any]) -> '{$className}':
        \"\"\"Create instance from dictionary\"\"\"
        return cls(**{k: v for k, v in data.items() if hasattr(cls, k)})
    
    def to_dict(self) -> Dict[str, Any]:
        \"\"\"Convert to dictionary\"\"\"
        return {k: v for k, v in self.__dict__.items() if v is not None}
";
    }

    protected function getPythonType(array $schema): string
    {
        $type = $schema['type'] ?? 'Any';
        
        $typeMap = [
            'string' => 'str',
            'integer' => 'int',
            'number' => 'float',
            'boolean' => 'bool',
            'array' => 'List[Any]',
            'object' => 'Dict[str, Any]'
        ];

        return $typeMap[$type] ?? 'Any';
    }

    // Helper methods
    protected function extractResources(array $openApiSpec): array
    {
        $resources = [];
        $paths = $openApiSpec['paths'] ?? [];

        foreach ($paths as $path => $pathItem) {
            foreach ($pathItem as $method => $operation) {
                if (!is_array($operation)) continue;

                $tags = $operation['tags'] ?? ['General'];
                $tag = $tags[0];
                
                if (!isset($resources[$tag])) {
                    $resources[$tag] = [
                        'name' => $tag,
                        'className' => $this->pascalCase($tag),
                        'methods' => []
                    ];
                }

                $resources[$tag]['methods'][] = [
                    'name' => $operation['operationId'] ?? $this->generateMethodName($method, $path),
                    'method' => strtoupper($method),
                    'path' => $path,
                    'summary' => $operation['summary'] ?? '',
                    'description' => $operation['description'] ?? '',
                    'parameters' => $operation['parameters'] ?? [],
                    'requestBody' => $operation['requestBody'] ?? null,
                    'responses' => $operation['responses'] ?? []
                ];
            }
        }

        return array_values($resources);
    }

    protected function extractPathParameters(string $path): array
    {
        preg_match_all('/\{([^}]+)\}/', $path, $matches);
        return $matches[1];
    }

    protected function isModelSchema(array $schema): bool
    {
        return isset($schema['type']) && 
               $schema['type'] === 'object' && 
               isset($schema['properties']) &&
               !isset($schema['allOf']);
    }

    protected function generateMethodName(string $method, string $path): string
    {
        $method = strtolower($method);
        $path = str_replace(['/', '{', '}'], ['_', '', ''], $path);
        return $method . $path;
    }

    protected function pascalCase(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $string)));
    }

    protected function snake_case(string $string): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string));
    }

    protected function generateResourceImports(array $resources): string
    {
        $imports = '';
        foreach ($resources as $resource) {
            $resourceName = $this->snake_case($resource['name']);
            $className = $this->pascalCase($resource['name']);
            $imports .= "from .resources.{$resourceName} import {$className}\n";
        }
        return $imports;
    }

    protected function generateResourceInitialization(array $resources): string
    {
        $init = '';
        foreach ($resources as $resource) {
            $resourceName = $this->snake_case($resource['name']);
            $className = $this->pascalCase($resource['name']);
            $init .= "        self.{$resourceName} = {$className}(self)\n";
        }
        return $init;
    }

    protected function generateResourcesInit(array $resources): void
    {
        $imports = '';
        $all = [];
        
        foreach ($resources as $resource) {
            $resourceName = $this->snake_case($resource['name']);
            $className = $this->pascalCase($resource['name']);
            $imports .= "from .{$resourceName} import {$className}\n";
            $all[] = "'{$className}'";
        }

        $content = "\"\"\"Resources module\"\"\"

{$imports}

__all__ = [" . implode(', ', $all) . "]
";

        $this->files['zippicks_sdk/resources/__init__.py'] = $content;
    }

    protected function generateModelsInit(array $schemas): void
    {
        $imports = '';
        $all = [];
        
        foreach ($schemas as $schemaName => $schema) {
            if ($this->isModelSchema($schema)) {
                $modelName = $this->snake_case($schemaName);
                $className = $this->pascalCase($schemaName);
                $imports .= "from .{$modelName} import {$className}\n";
                $all[] = "'{$className}'";
            }
        }

        $content = "\"\"\"Models module\"\"\"

{$imports}

__all__ = [" . implode(', ', $all) . "]
";

        $this->files['zippicks_sdk/models/__init__.py'] = $content;
    }

    public function getVersion(): string
    {
        return self::VERSION;
    }
}