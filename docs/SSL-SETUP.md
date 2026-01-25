# SSL/TLS Setup Guide

This guide covers setting up HTTPS for todo-me in production.

## Options

### Option 1: Let's Encrypt with Certbot (Recommended)

Free, automated SSL certificates from Let's Encrypt.

#### Prerequisites

- Domain name pointing to your server
- Ports 80 and 443 accessible

#### Setup Steps

1. **Install Certbot**
   ```bash
   # Ubuntu/Debian
   sudo apt install certbot

   # CentOS/RHEL
   sudo yum install certbot
   ```

2. **Obtain certificate**
   ```bash
   # Standalone mode (stop nginx first)
   sudo certbot certonly --standalone -d your-domain.com

   # Or webroot mode (nginx running)
   sudo certbot certonly --webroot -w /var/www/certbot -d your-domain.com
   ```

3. **Copy example config**
   ```bash
   cp docker/nginx/https.conf.example docker/nginx/https.conf
   ```

4. **Update config**
   Edit `docker/nginx/https.conf`:
   - Replace `your-domain.com` with your domain
   - Update certificate paths:
     ```nginx
     ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
     ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
     ```

5. **Mount certificates in Docker**
   Update `docker-compose.yml`:
   ```yaml
   services:
     nginx:
       volumes:
         - /etc/letsencrypt:/etc/letsencrypt:ro
         - ./nginx/https.conf:/etc/nginx/conf.d/default.conf:ro
   ```

6. **Setup auto-renewal**
   ```bash
   # Test renewal
   sudo certbot renew --dry-run

   # Add cron job
   echo "0 3 * * * /usr/bin/certbot renew --quiet" | sudo crontab -
   ```

### Option 2: Traefik Reverse Proxy

Automatic SSL with Traefik as a reverse proxy.

#### docker-compose.override.yml

```yaml
version: '3.8'

services:
  traefik:
    image: traefik:v2.10
    command:
      - "--api.insecure=true"
      - "--providers.docker=true"
      - "--providers.docker.exposedbydefault=false"
      - "--entrypoints.web.address=:80"
      - "--entrypoints.websecure.address=:443"
      - "--certificatesresolvers.letsencrypt.acme.httpchallenge=true"
      - "--certificatesresolvers.letsencrypt.acme.httpchallenge.entrypoint=web"
      - "--certificatesresolvers.letsencrypt.acme.email=your-email@example.com"
      - "--certificatesresolvers.letsencrypt.acme.storage=/letsencrypt/acme.json"
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - ./letsencrypt:/letsencrypt
    networks:
      - todo-network

  nginx:
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.todo.rule=Host(`your-domain.com`)"
      - "traefik.http.routers.todo.entrypoints=websecure"
      - "traefik.http.routers.todo.tls.certresolver=letsencrypt"
      - "traefik.http.routers.todo-http.rule=Host(`your-domain.com`)"
      - "traefik.http.routers.todo-http.entrypoints=web"
      - "traefik.http.routers.todo-http.middlewares=https-redirect"
      - "traefik.http.middlewares.https-redirect.redirectscheme.scheme=https"
    ports: []  # Remove direct port exposure
```

### Option 3: Cloud Load Balancer

Use your cloud provider's load balancer for SSL termination.

#### AWS ALB

1. Create Application Load Balancer
2. Add HTTPS listener on port 443
3. Configure ACM certificate
4. Target group pointing to your container on port 80

#### Cloudflare

1. Add site to Cloudflare
2. Enable "Full (strict)" SSL mode
3. Configure origin server with Cloudflare origin certificate
4. Cloudflare handles client-facing SSL

### Option 4: Self-Signed Certificate (Development Only)

For local development or testing:

```bash
# Generate self-signed certificate
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout docker/nginx/ssl/privkey.pem \
  -out docker/nginx/ssl/fullchain.pem \
  -subj "/CN=localhost"
```

Mount in docker-compose:
```yaml
nginx:
  volumes:
    - ./nginx/ssl:/etc/nginx/ssl:ro
```

## Verification

### Test SSL Configuration

```bash
# Check certificate
openssl s_client -connect your-domain.com:443 -servername your-domain.com

# Test SSL grade
# Visit: https://www.ssllabs.com/ssltest/
```

### Verify Headers

```bash
curl -I https://your-domain.com/api/v1/health
```

Expected headers:
```
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
```

## Troubleshooting

### Certificate Not Found

```
nginx: [emerg] cannot load certificate
```

Verify paths and permissions:
```bash
ls -la /etc/letsencrypt/live/your-domain.com/
```

### Port 80 Already in Use

Stop conflicting service or use webroot challenge:
```bash
sudo certbot certonly --webroot -w /var/www/certbot -d your-domain.com
```

### Mixed Content Warnings

Update application to use HTTPS:
```yaml
# .env.local
TRUSTED_PROXIES=127.0.0.1,REMOTE_ADDR
TRUSTED_HOSTS='^your-domain\.com$'
```

### Certificate Renewal Failed

Check certbot logs:
```bash
sudo certbot renew --dry-run --debug
```

## Security Recommendations

1. **Use TLS 1.2+**: Disable older protocols
2. **Enable HSTS**: Force HTTPS for browsers
3. **Use strong ciphers**: Follow Mozilla's recommendations
4. **Enable OCSP stapling**: Faster certificate verification
5. **Regular renewal**: Automate certificate renewal

## References

- [Let's Encrypt Documentation](https://letsencrypt.org/docs/)
- [Mozilla SSL Configuration Generator](https://ssl-config.mozilla.org/)
- [SSL Labs Test](https://www.ssllabs.com/ssltest/)
- [Traefik Documentation](https://doc.traefik.io/traefik/)
