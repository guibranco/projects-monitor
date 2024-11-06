## Deployment Guide for CPanel/FTP Hosting

This guide outlines the steps to enhance the deployment process, ensuring minimal downtime and maintaining application responsiveness during updates.

### 1. Deploy to a Transient Directory

1. Log in to your CPanel or FTP client.
2. Create a temporary directory (e.g., `temp_deploy`) in the root of your hosting environment.

### 2. Redirect Live Requests

1. Edit the `.htaccess` file in the root directory of your application.
2. Add the following lines to redirect requests to the temporary directory during deployment:

   ```apache
   # Redirect to temporary directory during deployment
   RewriteEngine On
   RewriteCond %{REQUEST_URI} !^/temp_deploy/
   RewriteRule ^(.*)$ /temp_deploy/$1 [L]
   ```

### 3. Deploy New Files

1. Upload the new version of the application to the `temp_deploy` directory using FTP or CPanel’s file manager.

### 4. Switch to New Version

1. Once testing is complete, update the `.htaccess` file to point to the new version by removing the temporary redirection:

   ```apache
   # Restore to the new version
   RewriteEngine On
   RewriteRule ^(.*)$ /$1 [L]
   ```

### 5. Remove Old Files

1. Delete the old version of the application files from the main directory.

### 6. Restore `.htaccess`

1. Ensure that the `.htaccess` file is correctly configured to point to the new version and includes necessary configurations for the live environment.

### 7. Clean Up Temporary Directory

1. Delete the `temp_deploy` directory after the new version is running smoothly.

This guide ensures a smooth transition between application versions, minimizing downtime and maintaining application responsiveness.