# Build the Docker image
build:
	docker-compose build

# Start the container in detached mode
up:
	docker-compose up -d

# Stop the container
down:
	docker-compose down

# Execute a command inside the container
exec:
	docker-compose exec app $(cmd)

# Run the main script
run:
	docker-compose exec app php bin/cli

# Get a shell inside the container
shell:
	docker-compose exec app bash

# Start only the redmine and postgres services
up-redmine:
	docker-compose up -d redmine postgres

# Clear log files
clear-logs:
	rm -rf var/log/*
	@echo "Log files cleared."
