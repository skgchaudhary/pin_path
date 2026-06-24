# PinPath — PHP built-in server backed by PostgreSQL (Supabase).
#
# The app is served by PHP's built-in web server. Even though the deploy
# config lists "Node", the start command is `php -S`, so the runtime must
# be PHP — this image provides it.

FROM php:8.2-cli

# PostgreSQL PDO driver (needs libpq headers to compile the extension).
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# App lives here; index.php is the document root entry point.
WORKDIR /app

# Copy the application source into the image.
COPY . /app

# Build step (kept to match the deploy config; nothing to compile).
RUN echo "No build"

# The platform injects $PORT; default to 8080 for plain `docker run`.
ENV PORT=8080
EXPOSE 8080

# Start command. `sh -c` so $PORT is expanded at runtime; -t sets the docroot.
CMD ["sh", "-c", "php -S 0.0.0.0:$PORT -t /app"]
