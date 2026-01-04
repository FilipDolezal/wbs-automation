# Build the Docker image
build:
	docker-compose build

# Start the application container
up:
	docker-compose up -d

# Stop the container
down:
	docker-compose down

# Get a shell inside the container
bash b:
	docker-compose exec app bash

# Clear log files
clear-logs:
	rm -rf var/log/*
	@echo "Log files cleared."

# Install PHP dependencies
composer-install ci:
	docker-compose exec app composer install