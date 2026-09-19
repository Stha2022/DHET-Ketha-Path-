FROM php:8.2-cli

RUN docker-php-ext-install mysqli

WORKDIR /var/www/html
COPY . .

# Render sets $PORT at runtime; default to 10000 for local `docker run`.
ENV PORT=10000
EXPOSE 10000

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT} -t /var/www/html"]
