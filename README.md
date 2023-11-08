# AlbaBoard

AlbaBoard is a Alba Board is a tool to manage tasks within the wordpress admin using the wordpress database.

## Tested with

- WordPress 6.4.1

## Requirements
![npm](https://img.shields.io/badge/npm-10.1.0-ba3333)
![node](https://img.shields.io/badge/node-20.6.1-79bf38)
![wordpress](https://img.shields.io/badge/wordpress-6.4-25698e)
![php](https://img.shields.io/badge/php-5.6-465185)
![docker](https://img.shields.io/badge/docker-24.0-1f57e3)
![wp-env](https://img.shields.io/badge/wp--env-8.11-black)

- WordPress 6.4+
- PHP 5.6+
- Node.js 20.6+
- npm 10.1+
- Docker 24.0+
- @wordpress/env 8.11+

### Not a developer?

This is a development version. Check later for the production version.

To get started right away:
```
git clone 
```

#### Requirements

In order to work with the development branch you need the following on your development environment:

- [Docker](https://www.docker.com)
- [Node.js](http://nodejs.org/)

We use wp-env to create a WordPress environment with our plugin already installated within.

Don't worry, once downloaded the `master` branch, only run the following code snippet in terminal to create the environment and run the plugin:

```
npm run wp-env start
```