# Nextcloud transient JWT Auth app
A [Nextcloud](https://nextcloud.com/) (v17+) application which lets you auto-login users ([single-sign-on](https://en.wikipedia.org/wiki/Single_sign-on)) without them having to go through the Nextcloud login page.

To make use of this app, you need another system which generates temporary [JWT](https://jwt.io/) tokens, serving as a login identifier.
The JWT Auth Nextcloud application securely processes these tokens and transparently logs the user into Nextcloud.

**Note**: Nextcloud must be deployed behind your gateway or proxy, which set JWT token in header. If you want use explicit login via your SSO - use the [original repo](https://github.com/devture/nextcloud-app-jwtauth) instead of this

Main idea is setting JWT token of user from outer system as `Authorization: Bearer JWT here` header for transparent login.

**Note**: Nextcloud v17+ is required.

## Flow

1. A user visits any Nextcloud page which requires authentication

2. If the user is not logged in, Nextcloud redirects the user to Nextcloud's login page (`/login`)

3. The JWT Auth app intercepts this request and forwards the user to your other system ([Identity Provider](#identity-provider-requirements)'s **auto-login-trigger endpoint**)

4. If not already logged in, the app will extract JWT from Authorization header.

5. The JWT Auth app validates the JWT token, and if trusted, transparently (without user action) logs the user into Nextcloud

6. The JWT Auth app redirects the user to the original page that the user tried to access (the one from step 1 above)


## Prerequisites for using

- users that you'd be logging in need to exist in Nextcloud. Whether you create them manually beforehand or you create them from another system using Nextcloud's [User Provisioning API](https://docs.nextcloud.com/server/16/admin_manual/configuration_user/instruction_set_for_users.html) is up to you.

- Gateway behind Nextcloud which set JWT token to each request to your Nextcloud instance.

### Example of systems which can be used as gateways for NextCloud

- Kong
- Spring Gateway
- Nginx with JWT support and SSO login

## Installation

This JWT Auth Nextcloud application is not available on the Nextcloud [App Store](https://apps.nextcloud.com/) yet, so you **need to install it manually**.

To install it, place its files in a `apps/jwtauth` directory.

Example: `git clone git@github.com:markfieldman/nextcloud-app-jwt-at-header-auth apps/jwtauth`.

Then install the app's dependencies using [composer](https://getcomposer.org/): 
```bash
cd apps/jwtauth; 
make composer 
cd ..
````
Copy file with public key to some folder on target machine

cp publicKey.pem /usr/share/nginx/html/nextcloud/

Edit config.php file with parameters  `'jwt.publicKey' => '$path-to-pem-file', 'jwt.alg' => 'RS256'`

`jwt.publicKey` - path to public key for JWT verification

`jwt.alg` - JWT algorithm

Finally, enable the app: `./occ app:enable jwtauth`.

If you want to enable autologin without opening login page - establish redirect from `index.php/login` to `index.php/apps/jwtauth`

From that point on, the Nextcloud `/login` page will be unavailable.

All other requests to the `/login` page would be automatically captured and validated by your Nextcloud instance.
