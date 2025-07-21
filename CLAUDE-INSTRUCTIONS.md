# ZipPicks Development Instructions for Claude Code

## Current Workflow
Claude Code builds directly into ~/Desktop/zippicks-final/ 
Files are then uploaded to production server.

## Project Structure
- `wp-content/` - WordPress integration
- `plugins/` - ZipPicks plugins
- `zippicks-foundation/` - Core platform code
- Root files - Configuration and documentation

## Build Requirements

### Every Feature Must Be:
1. **Server-Ready** - Works immediately when uploaded
2. **WordPress Compatible** - Integrates with existing WP structure
3. **Plugin Architecture** - Modular, self-contained
4. **Production Optimized** - Fast, secure, scalable

### File Generation Standards:
- Use existing folder structure
- Create new plugins in `plugins/zippicks-[feature-name]/`
- Update `wp-content/` for WordPress integration
- Include proper PHP headers and WordPress hooks
- Add readme files for each component

### Code Quality:
- WordPress coding standards
- Proper sanitization and validation
- Error handling with WordPress functions
- Database operations using WordPress APIs
- Security: nonces, capability checks, input validation
