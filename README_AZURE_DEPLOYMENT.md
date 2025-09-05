# AeroZone Azure Deployment Guide for Students

This guide will help you deploy your AeroZone airsoft community web portal to Microsoft Azure using the **Azure for Students** program, which provides free credits and resources for students.

## 🎓 Azure for Students Benefits

### Free Credits & Resources
- **$100 Azure credit** (no credit card required)
- **12 months of free services** including:
  - App Service (F1 tier)
  - Azure Database for PostgreSQL (32 GB)
  - Application Insights
  - Azure Storage (5 GB)
- **Always free services** (no time limit)
- **Student verification** through your educational email

### How to Get Started
1. Visit [Azure for Students](https://azure.microsoft.com/free/students/)
2. Sign up with your **educational email** (.edu domain)
3. Verify your student status
4. Get instant access to $100 credit

## Prerequisites

1. A GitHub repository with your AeroZone code
2. **Educational email address** (.edu domain)
3. Student verification (through your institution)
4. Azure CLI installed locally (optional)
5. Email service credentials (SendGrid recommended for Azure)

## Deployment Options

### Option 1: Azure Portal (Recommended for Beginners)

#### Step 1: Create Resource Group
1. Go to [Azure Portal](https://portal.azure.com)
2. Click "Create a resource" → "Resource group"
3. Name: `aerozone-rg`
4. Region: Choose closest to your users
5. Click "Review + create" → "Create"

#### Step 2: Deploy Database (Free Tier)
1. In your resource group, click "Create"
2. Search for "Azure Database for PostgreSQL"
3. Select "Flexible server"
4. Configure:
   - **Server name**: `aerozone-db-server`
   - **Admin username**: `aerozone_admin`
   - **Password**: Create a strong password
   - **Region**: Same as resource group
   - **Compute + storage**: **Burstable, B1ms (1 vCore, 2 GB RAM)** - FREE for students
   - **Storage**: 32 GB (included in free tier)
5. Click "Review + create" → "Create"

#### Step 3: Configure Database
1. Go to your PostgreSQL server
2. Click "Connection security"
3. Add firewall rule:
   - **Rule name**: `AllowAzureServices`
   - **Start IP**: `0.0.0.0`
   - **End IP**: `0.0.0.0`
4. Click "Save"

#### Step 4: Create Database
1. Go to "Databases" in your PostgreSQL server
2. Click "Add database"
3. **Database name**: `aerozone`
4. Click "Save"

#### Step 5: Deploy Web App (Free Tier)
1. In your resource group, click "Create"
2. Search for "Web App"
3. Configure:
   - **App name**: `aerozone-app` (must be globally unique)
   - **Runtime stack**: PHP 8.1
   - **Operating System**: Linux
   - **Region**: Same as resource group
   - **App Service Plan**: Create new (**F1 Free** - perfect for students)
4. Click "Review + create" → "Create"

#### Step 6: Configure App Settings
1. Go to your App Service
2. Click "Configuration" → "Application settings"
3. Add these settings:

```
APP_ENV = production
DB_HOST = your-server-name.postgres.database.azure.com
DB_PORT = 5432
DB_NAME = aerozone
DB_USER = aerozone_admin
DB_PASSWORD = your-database-password
EMAIL_SMTP_HOST = smtp.sendgrid.net
EMAIL_SMTP_PORT = 587
EMAIL_SMTP_USERNAME = apikey
EMAIL_SMTP_PASSWORD = your-sendgrid-api-key
EMAIL_FROM_NAME = AEROZONE
EMAIL_FROM_ADDRESS = your-email@domain.com
```

#### Step 7: Deploy Code
1. Go to "Deployment Center" in your App Service
2. Choose "GitHub" as source
3. Authorize and select your repository
4. Branch: `main`
5. Click "Save"

### Option 2: Azure CLI (Advanced Users)

#### Prerequisites
```bash
# Install Azure CLI
curl -sL https://aka.ms/InstallAzureCLIDeb | sudo bash

# Login to Azure
az login

# Set subscription (if you have multiple)
az account set --subscription "Your Subscription Name"
```

#### Deploy Infrastructure
```bash
# Create resource group
az group create --name aerozone-rg --location "East US"

# Deploy using ARM template
az deployment group create \
  --resource-group aerozone-rg \
  --template-file azure-template.json \
  --parameters administratorLoginPassword="YourSecurePassword123!"
```

#### Deploy Application
```bash
# Deploy from GitHub
az webapp deployment source config \
  --resource-group aerozone-rg \
  --name aerozone-app \
  --repo-url https://github.com/yourusername/aerozone \
  --branch main \
  --manual-integration
```

### Option 3: Azure DevOps (CI/CD)

1. Create Azure DevOps project
2. Import your repository
3. Use the provided `azure-deploy.yml` pipeline
4. Configure service connections
5. Run the pipeline

## Email Configuration

### SendGrid Setup (Recommended)
1. Create [SendGrid account](https://sendgrid.com)
2. Verify your sender identity
3. Create API key with "Mail Send" permissions
4. Use these settings:
   - **SMTP Host**: `smtp.sendgrid.net`
   - **Port**: `587`
   - **Username**: `apikey`
   - **Password**: Your SendGrid API key

### Alternative Email Services
- **Office 365**: Use your Office 365 SMTP settings
- **Gmail**: Use Gmail SMTP with App Password
- **Mailgun**: Professional email service

## Initialize Database

1. Once deployed, visit: `https://your-app-name.azurewebsites.net/azure-deploy.php`
2. This will:
   - Create all database tables
   - Set up indexes and triggers
   - Create default admin user
3. Default admin credentials:
   - **Username**: `admin`
   - **Password**: `admin123`
   - **⚠️ Change this immediately!**

## WebSocket Configuration

For real-time chat functionality:

1. Create a separate App Service for WebSocket
2. Use the same configuration as main app
3. Set start command: `php websocket/chat-server.php`
4. Update WebSocket URL in main app settings

## Custom Domain Setup

1. In App Service, go to "Custom domains"
2. Add your domain
3. Configure DNS records as instructed
4. SSL certificate will be automatically provisioned

## Monitoring and Logging

### Application Insights
1. Create Application Insights resource
2. Connect to your App Service
3. Monitor performance and errors

### Log Streaming
```bash
# View real-time logs
az webapp log tail --resource-group aerozone-rg --name aerozone-app
```

### Health Checks
- Set up health check endpoint
- Configure auto-scaling rules
- Set up alerts for downtime

## Security Configuration

### SSL/TLS
- Azure provides free SSL certificates
- Force HTTPS redirect (configured in web.config)
- Use HSTS headers

### Database Security
- Enable SSL connections
- Use connection pooling
- Regular security updates

### Application Security
- Environment variables for secrets
- Input validation and sanitization
- Regular dependency updates

## Performance Optimization

### Caching
- Enable Redis Cache for session storage
- Use CDN for static assets
- Configure browser caching

### Database Optimization
- Monitor query performance
- Add appropriate indexes
- Use connection pooling

### Scaling
- Configure auto-scaling rules
- Use multiple instances
- Consider Premium plans for production

## 💰 Cost Management for Students

### 🆓 Free Tier Benefits (Azure for Students)
- **App Service F1**: 1 GB RAM, 1 GB storage, 60 minutes/day compute
- **Database**: 32 GB storage, 1 vCore, 7 days backup retention
- **Bandwidth**: 165 GB/month
- **Storage**: 5 GB blob storage
- **Application Insights**: 5 GB data ingestion/month

### 📊 Student Credit Usage
- **$100 credit** lasts approximately 6-12 months for this project
- **No charges** for free tier services
- **Monitor usage** in Azure Cost Management
- **Set up alerts** when approaching credit limits

### 💡 Cost Optimization Tips
- Use **F1 Free** App Service plan (perfect for learning)
- Choose **Burstable B1ms** database (included in free tier)
- Enable **auto-shutdown** for development environments
- Use **Azure Cost Management** to track spending
- **Delete resources** when not in use to save credits

## Troubleshooting

### Common Issues

1. **Database Connection Errors**
   ```bash
   # Check connection string
   az webapp config appsettings list --resource-group aerozone-rg --name aerozone-app
   ```

2. **Deployment Failures**
   - Check build logs in Deployment Center
   - Verify composer.json dependencies
   - Check PHP version compatibility

3. **Email Not Sending**
   - Verify SendGrid API key
   - Check sender verification
   - Test with simple email first

4. **File Upload Issues**
   - Check file size limits in web.config
   - Verify upload directory permissions
   - Test with smaller files

### Getting Help

1. **Azure Documentation**: [docs.microsoft.com/azure](https://docs.microsoft.com/azure)
2. **Community Support**: [Azure Community](https://azure.microsoft.com/support/community/)
3. **Application Logs**: Check in App Service → Log stream

## Backup and Recovery

### Database Backups
- Azure provides automatic backups
- Configure point-in-time recovery
- Test restore procedures

### Application Backups
- Use Azure Backup for App Service
- Store code in version control
- Document deployment procedures

## Next Steps

1. **Set up monitoring and alerting**
2. **Configure automated backups**
3. **Implement CI/CD pipeline**
4. **Set up staging environment**
5. **Plan for scaling and high availability**

Your AeroZone web portal is now successfully deployed on Azure! 🎉

## Support and Resources

- [Azure App Service Documentation](https://docs.microsoft.com/azure/app-service/)
- [Azure Database for PostgreSQL Documentation](https://docs.microsoft.com/azure/postgresql/)
- [PHP on Azure Best Practices](https://docs.microsoft.com/azure/app-service/tutorial-php-mysql-app)
- [Azure Pricing Calculator](https://azure.microsoft.com/pricing/calculator/)
