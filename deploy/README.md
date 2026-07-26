# PHP error handling

The production NOC uses PHP's per-directory `.user.ini` mechanism.

The file is deployed as:

/home/arcanel/public_html/noc/.user.ini

PHP is configured with:

user_ini.filename = .user.ini

The configuration:
- disables error output to HTTP clients;
- keeps E_ALL diagnostics enabled;
- writes errors to a private directory outside the web root.

The private log location is:

/home/arcanel/web_safe/noc/errors/php-error.log

## Deployment

Deployment is not automatic. Use ssh to login to the web hotel, then copy `deploy/php/.user.ini` to the NOC document root as `.user.ini`