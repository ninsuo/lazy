# Usage without lazy

This documentation summarize the steps that lazy use to install a new certificate.

## Secure your website

To add a certificate without any user interaction:

```sh
sudo certbot --non-interactive --agree-tos --email alain@fuz.org --apache --domains beast.systems
 ```

To remove one certificate without any user interaction:

```sh
sudo rm /etc/apache2/sites-enabled/000-beast.systems-le-ssl.conf
sudo certbot delete --non-interactive --apache --agree-tos --cert-name beast.systems
sudo service apache2 restart
```

You may need to regenerate your `000-beast.systems.conf` so it doesn't redirect to HTTPS.
