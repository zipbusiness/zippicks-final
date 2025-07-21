#!/bin/bash

# ZipPicks Repository Migration Script
# Run this script to transform your repository into enterprise-grade structure

echo "🚀 Starting ZipPicks Repository Migration..."

# Phase 1: Archive existing files
echo "📦 Phase 1: Archiving existing files..."
mkdir -p .archive/legacy-files
mv "$100B Enterprise Engineering Protocol" .archive/legacy-files/ 2>/dev/null || true
mv ".ZIPPICKS_PLUGIN_LOCK.yaml" .archive/legacy-files/ 2>/dev/null || true
mv "Business Plan" .archive/legacy-files/ 2>/dev/null || true
mv "README_DB_LOCK.md" .archive/legacy-files/ 2>/dev/null || true
mv "Vibes DB Structure" .archive/legacy-files/ 2>/dev/null || true
mv "ZipPicks Canonical Table Aliasing System" .archive/legacy-files/ 2>/dev/null || true
mv "ZipPicks Plugin Development Protocol" .archive/legacy-files/ 2>/dev/null || true
mv "ZipPicks Plugin Development Protocol 1.0" .archive/legacy-files/ 2>/dev/null || true
mv "ZipPicks User Role System - Engineering Guide" .archive/legacy-files/ 2>/dev/null || true
mv "business-plan.txt" .archive/legacy-files/ 2>/dev/null || true
mv "functions.php" .archive/legacy-files/ 2>/dev/null || true
mv "platform-architecture.txt" .archive/legacy-files/ 2>/dev/null || true

echo "✅ Legacy files archived successfully"

# Phase 2: Create directory structure
echo "🏗️ Phase 2: Creating enterprise directory structure..."

# GitHub configuration
mkdir -p .github/{workflows,ISSUE_TEMPLATE}

# Claude configuration
mkdir -p .claude/{templates,workflows}

# Application structure
mkdir -p apps/{web,mobile,admin}
mkdir -p apps/web/src/{app,components,hooks,lib,types}
mkdir -p apps/web/public
mkdir -p apps/mobile/src/{screens,components,navigation,hooks,utils}
mkdir -p apps/mobile/{android,ios}
mkdir -p apps/admin/src

# Microservices
mkdir -p services/{api-gateway,auth-service,user-service,recommendation-engine,content-service,analytics-service,notification-service,search-service}

# Shared packages
mkdir -p packages/{ui,utils,types,config,database,auth,api-client}

# Infrastructure
mkdir -p infrastructure/{terraform,kubernetes,docker,monitoring}
mkdir -p infrastructure/terraform/{environments/{dev,staging,production},modules,global}

# Database
mkdir -p database/{migrations,seeds,schemas,scripts}

# Documentation
mkdir -p docs/{api,architecture,deployment,development,user}

# Tools and testing
mkdir -p tools/{scripts,generators,validators}
mkdir -p tests/{e2e,load,security,fixtures}

echo "✅ Directory structure created"

# Phase 3: Initialize package.json files
echo "📦 Phase 3: Initializing package configurations..."

# Root package.json (monorepo)
cat > package.json << 'EOF'
{
  "name": "zippicks",
  "version": "1.0.0",
  "private": true,
  "description": "ZipPicks - $1B Enterprise Platform",
  "workspaces": [
    "apps/*",
    "services/*",
    "packages/*"
  ],
  "scripts": {
    "dev": "turbo run dev",
    "build": "turbo run build",
    "test": "turbo run test",
    "test:e2e": "playwright test",
    "lint": "turbo run lint",
    "type-check": "turbo run type-check",
    "clean": "turbo run clean",
    "deploy:dev": "turbo run deploy --filter=dev",
    "deploy:staging": "turbo run deploy --filter=staging",
    "deploy:prod": "turbo run deploy --filter=production",
    "db:migrate": "npm run db:migrate --workspace=database",
    "db:seed": "npm run db:seed --workspace=database",
    "security:scan": "npm audit && turbo run security:scan",
    "performance:test": "k6 run tests/load/performance.js"
  },
  "devDependencies": {
    "@anthropic/claude-code": "^1.0.0",
    "@turbo/gen": "^1.10.0",
    "turbo": "^1.10.0",
    "typescript": "^5.0.0",
    "@types/node": "^20.0.0",
    "prettier": "^3.0.0",
    "eslint": "^8.0.0",
    "playwright": "^1.40.0",
    "k6": "^0.47.0"
  },
  "engines": {
    "node": ">=20.0.0",
    "npm": ">=10.0.0"
  }
}
EOF

# Turbo configuration
cat > turbo.json << 'EOF'
{
  "$schema": "https://turbo.build/schema.json",
  "globalDependencies": ["**/.env.*local"],
  "pipeline": {
    "build": {
      "dependsOn": ["^build"],
      "outputs": [".next/**", "!.next/cache/**", "dist/**"]
    },
    "dev": {
      "cache": false,
      "persistent": true
    },
    "test": {
      "dependsOn": ["build"],
      "outputs": ["coverage/**"]
    },
    "lint": {},
    "type-check": {},
    "clean": {
      "cache": false
    },
    "deploy": {
      "dependsOn": ["build", "test"],
      "cache": false
    }
  }
}
EOF

# Docker Compose for local development
cat > docker-compose.yml << 'EOF'
version: '3.8'

services:
  postgres:
    image: postgres:15
    environment:
      POSTGRES_DB: zippicks_dev
      POSTGRES_USER: zippicks
      POSTGRES_PASSWORD: dev_password
    ports:
      - "5432:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./database/init.sql:/docker-entrypoint-initdb.d/init.sql

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"

  elasticsearch:
    image: docker.elastic.co/elasticsearch/elasticsearch:8.11.0
    environment:
      - discovery.type=single-node
      - xpack.security.enabled=false
    ports:
      - "9200:9200"

  api-gateway:
    build: ./services/api-gateway
    ports:
      - "3000:3000"
    depends_on:
      - postgres
      - redis
    environment:
      - NODE_ENV=development
      - DATABASE_URL=postgresql://zippicks:dev_password@postgres:5432/zippicks_dev

volumes:
  postgres_data:
EOF

echo "✅ Package configurations initialized"

# Phase 4: Create essential configuration files
echo "⚙️ Phase 4: Creating configuration files..."

# Environment template
cat > .env.example << 'EOF'
# Database
DATABASE_URL=postgresql://username:password@localhost:5432/zippicks_dev
REDIS_URL=redis://localhost:6379

# Authentication
JWT_SECRET=your-super-secret-jwt-key
JWT_EXPIRES_IN=7d

# API Keys
CLAUDE_API_KEY=your-claude-api-key
OPENAI_API_KEY=your-openai-api-key

# AWS (for production)
AWS_ACCESS_KEY_ID=your-aws-access-key
AWS_SECRET_ACCESS_KEY=your-aws-secret-key
AWS_REGION=us-west-2

# Monitoring
SENTRY_DSN=your-sentry-dsn
DATADOG_API_KEY=your-datadog-key

# Feature Flags
ENABLE_REAL_TIME=true
ENABLE_RECOMMENDATIONS=true
EOF

# Updated .gitignore
cat > .gitignore << 'EOF'
# Dependencies
node_modules/
npm-debug.log*
yarn-debug.log*
yarn-error.log*

# Environment files
.env
.env.local
.env.development.local
.env.test.local
.env.production.local

# Build outputs
.next/
dist/
build/
coverage/

# Database
*.db
*.sqlite

# Logs
logs/
*.log

# IDE
.vscode/
.idea/
*.swp
*.swo

# OS
.DS_Store
Thumbs.db

# Testing
__tests__/coverage/
.nyc_output/

# Temporary files
.tmp/
temp/

# Docker
.docker/

# Terraform
*.tfstate
*.tfstate.*
.terraform/
EOF

echo "✅ Configuration files created"

# Phase 5: Initialize Git setup
echo "🔧 Phase 5: Setting up Git configuration..."

# Create initial README
cat > README.md << 'EOF'
# 🚀 ZipPicks - Enterprise Platform

> Building the future of content discovery and engagement at $1B scale

## 🏗️ Architecture

ZipPicks is built as a modern, scalable platform using:
- **Microservices Architecture** for independent scaling
- **Turborepo Monorepo** for unified development experience  
- **Claude Code Integration** for AI-assisted development
- **Enterprise-grade Security** and performance optimization

## 🚦 Quick Start

```bash
# Install dependencies
npm install

# Start development environment
docker-compose up -d
npm run dev

# Run tests
npm test

# Deploy to staging
npm run deploy:staging
```

## 📁 Project Structure

- `apps/` - Frontend applications (web, mobile, admin)
- `services/` - Backend microservices
- `packages/` - Shared libraries and utilities
- `infrastructure/` - Infrastructure as Code
- `database/` - Database schemas and migrations
- `docs/` - Project documentation

## 🤖 Claude Code Integration

This project uses Claude Code for:
- Automated code reviews
- Performance optimization
- Security analysis
- Documentation generation
- Test generation

## 🔧 Development

See [Development Guide](docs/development/README.md) for detailed setup instructions.

## 🚀 Deployment

See [Deployment Guide](docs/deployment/README.md) for deployment procedures.

## 📊 Monitoring

- Performance: [Grafana Dashboard](http://localhost:3001)
- Logs: [ELK Stack](http://localhost:5601)
- Errors: [Sentry](https://sentry.io)

## 🤝 Contributing

See [Contributing Guide](CONTRIBUTING.md) for development guidelines.

---

Built with ❤️ by the ZipPicks team
EOF

echo "✅ Git configuration complete"

echo ""
echo "🎉 ZipPicks Repository Migration Complete!"
echo ""
echo "📋 Next Steps:"
echo "1. Review the new structure in your file explorer"
echo "2. Run 'npm install' to install dependencies"
echo "3. Copy environment variables: 'cp .env.example .env'"
echo "4. Start development: 'docker-compose up -d && npm run dev'"
echo "5. Create your first feature using Claude Code"
echo ""
echo "🔗 Useful Commands:"
echo "- npm run dev          # Start all services"
echo "- npm run build        # Build all packages"
echo "- npm test             # Run all tests"
echo "- npm run deploy:dev   # Deploy to development"
echo ""
echo "📚 Documentation: ./docs/"
echo "🏗️ Architecture: ./docs/architecture/"
echo "🚀 Happy coding with Claude!"
EOF