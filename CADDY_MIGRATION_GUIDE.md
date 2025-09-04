# 🔄 Nginx to Caddy Migration Guide

## Migration Overview

This guide will help you migrate from nginx to Caddy while maintaining all security features including Laravel Sanctum CSRF protection, rate limiting, and proper cookie handling.

## 📋 Pre-Migration Checklist

- [ ] Backup current nginx configuration
- [ ] Backup SSL certificates
- [ ] Backup Docker volumes
- [ ] Test current system functionality
- [ ] Prepare rollback plan

## 🔧 Step-by-Step Migration

### Step 1: Backup Current System

```bash
# Create backup directory
mkdir -p backups/$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="backups/$(date +%Y%m%d_%H%M%S)"

# Backup nginx configuration
cp -r nginx/ $BACKUP_DIR/
cp docker-compose.yml $BACKUP_DIR/

# Backup Docker volumes
docker run --rm -v secure-login-app_postgres_data:/data -v $(pwd)/$BACKUP_DIR:/backup alpine tar czf /backup/postgres_data.tar.gz -C /data .

echo "✅ Backup completed in $BACKUP_DIR"
```

### Step 2: Stop Current Services

```bash
# Stop all containers
docker compose down

# Optional: Remove nginx container and image
docker rm secure-app-nginx
docker rmi nginx:alpine
```

### Step 3: Deploy Caddy Configuration

The Caddy configuration has been created at `caddy/Caddyfile` with:

✅ **CSRF Protection**: Proper header forwarding for Laravel Sanctum
✅ **Rate Limiting**: 5 requests/minute for login routes, 100/minute for API
✅ **Security Headers**: Comprehensive set including HSTS, CSP, X-Frame-Options
✅ **CORS Configuration**: Restricted to known origins with credential support
✅ **HTTPS Enforcement**: Automatic HTTP to HTTPS redirects
✅ **SSL Handling**: Reuses existing certificates + auto-cert capability
✅ **Structured Logging**: JSON logs for security monitoring

### Step 4: Start Caddy Services

```bash
# Start with Caddy
docker compose -f docker-compose-caddy.yml up -d

# Check logs
docker logs secure-app-caddy

# Verify all containers are running
docker ps
```

### Step 5: Test Migration

**Windows (PowerShell):**
```powershell
.\test-caddy-security.ps1
```

**Linux/macOS (Bash):**
```bash
chmod +x test-caddy-security.sh
./test-caddy-security.sh
```

## 🔍 Key Improvements Over Nginx

### Enhanced Security Features

1. **Automatic HTTPS**: Caddy can auto-generate and renew certificates
2. **Built-in Rate Limiting**: More flexible than nginx rate limiting
3. **Structured Logging**: JSON format for better security monitoring
4. **Better Error Handling**: Custom JSON error responses
5. **Advanced CORS**: More granular control over CORS policies

### Configuration Simplicity

- **Single File**: All configuration in one Caddyfile
- **No Complex Syntax**: Human-readable configuration
- **Auto-reload**: Configuration reloads without restarts
- **Built-in Features**: No need for additional modules

## 🚨 Troubleshooting

### Common Issues

**Issue: 502 Bad Gateway**
```bash
# Check if backend services are running
docker logs secure-app-backend
docker logs secure-app-frontend

# Check Caddy logs
docker logs secure-app-caddy
```

**Issue: CSRF Token Mismatch**
```bash
# Verify CSRF cookie is being set
curl -I https://localhost:8443/sanctum/csrf-cookie

# Check Caddy is forwarding headers correctly
docker logs secure-app-caddy | grep "X-XSRF-TOKEN"
```

**Issue: Rate Limiting Not Working**
```bash
# Check rate limit configuration
docker exec secure-app-caddy caddy validate --config /etc/caddy/Caddyfile

# Test rate limiting manually
for i in {1..10}; do curl -X POST https://localhost:8443/login; done
```

## 📊 Security Checklist Verification

After migration, verify these security features:

- [ ] ✅ CSRF handshake required before unsafe requests
- [ ] ✅ Cookies are HttpOnly + Secure (and SameSite=Lax)
- [ ] ✅ Login route rate-limited (429 verified)
- [ ] ✅ Server-side validation for email/password (rejects bad input)
- [ ] ✅ Passwords hashed (bcrypt) in DB
- [ ] ✅ CORS restricted to known origin(s)
- [ ] ✅ HTTPS used (or documented production plan) & Secure cookies enabled
- [ ] ✅ Email verification & password reset tested (mailer log)
- [ ] ✅ Basic security headers observed (show response headers)
- [ ] ✅ Logs show failed logins and logout events

## 🔄 Rollback Plan

If issues occur, quickly rollback:

```bash
# Stop Caddy services
docker compose -f docker-compose-caddy.yml down

# Restore nginx services
docker compose up -d

# Verify nginx is working
curl -I https://localhost:8443
```

## 🎯 Production Considerations

### DNS and Certificates

For production, update your Caddyfile:

```caddy
# Replace localhost with your domain
yourdomain.com {
    # Caddy will automatically get Let's Encrypt certificates
    # ... rest of configuration
}
```

### Environment Variables

Consider using environment variables for production:

```caddy
{$DOMAIN:localhost:8443} {
    # Configuration using environment variables
    rate_limit @login_routes {
        zone login_zone
        key {remote_host}
        events {$LOGIN_RATE_LIMIT:5}
        window {$LOGIN_RATE_WINDOW:1m}
    }
}
```

### Monitoring

Set up log monitoring:

```bash
# Monitor Caddy access logs
tail -f /var/lib/docker/volumes/secure-login-app_caddy_logs/_data/access.log

# Monitor security events
tail -f /var/lib/docker/volumes/secure-login-app_caddy_logs/_data/security.log
```

## 📈 Performance Benefits

- **Reduced Memory Usage**: Caddy typically uses less memory than nginx
- **Better HTTP/2 Support**: Built-in HTTP/2 and HTTP/3 support
- **Faster Configuration Reloads**: No downtime for config changes
- **Automatic Compression**: Built-in gzip/brotli compression

## 🏁 Migration Complete

Your system now uses Caddy with enhanced security features while maintaining full compatibility with your Laravel Sanctum authentication system.