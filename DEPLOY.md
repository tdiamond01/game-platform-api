# AWS Deployment Guide

This guide walks through deploying the Game Platform API to AWS using Elastic Beanstalk, RDS MySQL, and ElastiCache Redis.

## Architecture

```
                    ┌─────────────────┐
                    │   CloudFront    │  ← Optional: SSL + CDN
                    └────────┬────────┘
                             │
                    ┌────────▼────────┐
                    │ Elastic         │  ← PHP 8.2 + nginx
                    │ Beanstalk       │     Auto-scaling
                    └────────┬────────┘
                             │
         ┌───────────────────┼───────────────────┐
         │                   │                   │
    ┌────▼────┐        ┌─────▼─────┐       ┌─────▼─────┐
    │   RDS   │        │  ElastiCache │    │    S3     │
    │  MySQL  │        │   Redis      │    │  (assets) │
    └─────────┘        └───────────────┘    └───────────┘
```

## Prerequisites

1. AWS CLI installed and configured
2. EB CLI installed (`pip install awsebcli`)
3. AWS account with appropriate permissions

```bash
# Install EB CLI
pip install awsebcli

# Configure AWS credentials
aws configure
```

---

## Step 1: Create RDS MySQL Database

### Via AWS Console:
1. Go to RDS → Create Database
2. Choose **MySQL 8.0**
3. Select **Free tier** (for testing) or **Production**
4. Settings:
   - DB instance identifier: `game-platform-db`
   - Master username: `admin`
   - Master password: (save this securely)
5. Instance configuration:
   - db.t3.micro (testing) or db.t3.small+ (production)
6. Storage: 20 GB gp2 (can auto-scale)
7. Connectivity:
   - VPC: Default VPC
   - Public access: No (EB will connect internally)
   - Security group: Create new → `game-platform-db-sg`
8. Database name: `game_platform`
9. Create database

### Via CLI:
```bash
aws rds create-db-instance \
  --db-instance-identifier game-platform-db \
  --db-instance-class db.t3.micro \
  --engine mysql \
  --engine-version 8.0 \
  --master-username admin \
  --master-user-password YOUR_SECURE_PASSWORD \
  --allocated-storage 20 \
  --db-name game_platform \
  --vpc-security-group-ids sg-xxxxxxxxx \
  --no-publicly-accessible
```

**Save the endpoint** (e.g., `game-platform-db.xxxx.us-west-2.rds.amazonaws.com`)

---

## Step 2: Create ElastiCache Redis Cluster

### Via AWS Console:
1. Go to ElastiCache → Redis clusters → Create
2. Cluster mode: **Disabled** (single node for testing)
3. Name: `game-platform-cache`
4. Node type: `cache.t3.micro` (testing) or `cache.t3.small+` (production)
5. Number of replicas: 0 (testing) or 1+ (production)
6. Subnet group: Create new or use default
7. Security group: Create new → `game-platform-cache-sg`
8. Create

### Via CLI:
```bash
aws elasticache create-cache-cluster \
  --cache-cluster-id game-platform-cache \
  --cache-node-type cache.t3.micro \
  --engine redis \
  --num-cache-nodes 1 \
  --security-group-ids sg-xxxxxxxxx
```

**Save the endpoint** (e.g., `game-platform-cache.xxxx.cache.amazonaws.com`)

---

## Step 3: Initialize Elastic Beanstalk

```bash
cd /path/to/game-platform-api

# Initialize EB application
eb init

# Follow prompts:
# - Select region (e.g., us-west-2)
# - Application name: game-platform-api
# - Platform: PHP 8.2
# - SSH: Yes (for debugging)
```

This creates `.elasticbeanstalk/config.yml`.

---

## Step 4: Create Elastic Beanstalk Environment

```bash
# Create the environment
eb create game-platform-prod \
  --instance-type t3.small \
  --single \
  --database.engine mysql \
  --database.version 8.0 \
  --database.instance db.t3.micro \
  --database.size 20 \
  --database.username admin \
  --database.password YOUR_PASSWORD
```

Or create without bundled database (using existing RDS):
```bash
eb create game-platform-prod \
  --instance-type t3.small \
  --single
```

---

## Step 5: Configure Environment Variables

### Via EB Console:
1. Go to Elastic Beanstalk → Environments → game-platform-prod
2. Configuration → Software → Edit
3. Add Environment properties:

### Via CLI:
```bash
eb setenv \
  APP_KEY=base64:YOUR_GENERATED_KEY \
  APP_ENV=production \
  APP_DEBUG=false \
  APP_URL=https://your-domain.com \
  DB_CONNECTION=mysql \
  DB_HOST=game-platform-db.xxxx.us-west-2.rds.amazonaws.com \
  DB_DATABASE=game_platform \
  DB_USERNAME=admin \
  DB_PASSWORD=YOUR_DB_PASSWORD \
  REDIS_HOST=game-platform-cache.xxxx.cache.amazonaws.com \
  CACHE_STORE=redis \
  SESSION_DRIVER=redis \
  QUEUE_CONNECTION=redis \
  CLAUDE_API_KEY=sk-ant-YOUR_KEY \
  LOG_CHANNEL=stderr
```

Generate APP_KEY locally:
```bash
php artisan key:generate --show
# Output: base64:xxxxxxxxxxxxxxxxxxxx
```

---

## Step 6: Configure Security Groups

The EB instances need to connect to RDS and ElastiCache. Update security groups:

### RDS Security Group (`game-platform-db-sg`):
```
Inbound Rules:
- Type: MySQL/Aurora (3306)
- Source: EB security group (sg-xxxxxxxxx)
```

### ElastiCache Security Group (`game-platform-cache-sg`):
```
Inbound Rules:
- Type: Custom TCP (6379)
- Source: EB security group (sg-xxxxxxxxx)
```

---

## Step 7: Deploy

```bash
# Deploy the application
eb deploy

# View logs if issues
eb logs

# Open in browser
eb open

# SSH into instance (debugging)
eb ssh
```

---

## Step 8: Run Initial Setup

SSH into the instance and run setup commands:

```bash
eb ssh

# On the instance:
cd /var/app/current

# Run migrations (should happen automatically, but verify)
sudo -u webapp php artisan migrate --force

# Seed the database
sudo -u webapp php artisan db:seed --force

# Generate initial challenges
sudo -u webapp php artisan challenges:generate --days=7

# Verify everything is working
sudo -u webapp php artisan tinker
>>> App\Models\Game::count()
>>> App\Models\DailyChallenge::count()
>>> exit
```

---

## Step 9: Set Up Custom Domain (Optional)

### With CloudFront:
1. Create CloudFront distribution
2. Origin: EB environment URL
3. SSL Certificate: Request via ACM
4. Update Route 53 to point domain to CloudFront

### Without CloudFront:
1. Go to EB → Configuration → Load Balancer
2. Add HTTPS listener (port 443)
3. Upload SSL certificate or use ACM
4. Update Route 53 to point to EB URL

---

## Monitoring & Maintenance

### View Logs:
```bash
eb logs                    # Recent logs
eb logs --all              # All logs
eb logs -cw enable         # Stream to CloudWatch
```

### Health Check:
```bash
curl https://your-domain.com/v1/health
# Should return: {"status": "ok"}
```

### Scale Up/Down:
```bash
eb scale 3                 # Scale to 3 instances
eb scale 1                 # Scale back to 1
```

### Update Environment:
```bash
eb setenv NEW_VAR=value    # Add/update env var
eb deploy                  # Deploy code changes
```

---

## Troubleshooting

### Common Issues:

**1. 502 Bad Gateway**
- Check PHP-FPM is running: `eb ssh` → `systemctl status php-fpm`
- Check nginx logs: `/var/log/nginx/error.log`

**2. Database Connection Failed**
- Verify security group allows EB → RDS
- Check DB_HOST, DB_USERNAME, DB_PASSWORD
- Test: `mysql -h your-rds-endpoint -u admin -p`

**3. Redis Connection Failed**
- Verify security group allows EB → ElastiCache
- Check REDIS_HOST
- ElastiCache must be in same VPC as EB

**4. Migrations Failed**
- SSH in and run manually: `php artisan migrate --force`
- Check database permissions

**5. Challenges Not Generating**
- Verify CLAUDE_API_KEY is set
- Check cron is running: `cat /etc/cron.d/game-platform`
- Run manually: `php artisan challenges:generate`

---

## Cost Estimates (US West 2)

### Development/Testing:
| Service | Instance | Monthly Cost |
|---------|----------|--------------|
| EB | t3.micro | ~$8 |
| RDS | db.t3.micro | ~$15 |
| ElastiCache | cache.t3.micro | ~$12 |
| **Total** | | **~$35/month** |

### Production (light traffic):
| Service | Instance | Monthly Cost |
|---------|----------|--------------|
| EB | t3.small (2x) | ~$30 |
| RDS | db.t3.small | ~$25 |
| ElastiCache | cache.t3.small | ~$24 |
| CloudFront | 100GB transfer | ~$10 |
| **Total** | | **~$90/month** |

---

## Quick Reference Commands

```bash
# Deployment
eb deploy                  # Deploy changes
eb deploy --staged         # Deploy staged git changes

# Environment
eb status                  # Environment status
eb health                  # Detailed health
eb setenv KEY=value        # Set env variable
eb printenv                # Print all env vars

# Logs
eb logs                    # Recent logs
eb logs -a                 # All logs

# Access
eb open                    # Open in browser
eb ssh                     # SSH to instance

# Scaling
eb scale N                 # Scale to N instances

# Maintenance
eb terminate               # Delete environment
eb restore                 # Restore terminated env
```
