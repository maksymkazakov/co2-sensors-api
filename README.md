## Installation

Ideally, there would be a single command to build and start a Docker container, but with my current local setup I couldn't debug it properly, so there are prerequisites:
- php7+
- Docker

```bash
# get Composer and install dependencies
sh scripts/download-composer.sh
php composer.phar install --ignore-platform-reqs --no-scripts

# build Docker image and run a container
docker build . -t co2
docker run --rm -p 8000:80 --name co2 -v $(pwd):/var/www -w=/var/www co2

# create database tables
docker exec -it co2 php artisan migrate
```

## Running tests
```bash
docker exec -it co2 php artisan test
```
