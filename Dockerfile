FROM php:8.2-cli

RUN docker-php-ext-install mysqli

# Production: warnings go to Render's log, never into the page (where they would
# break headers and sessions), and output is buffered so a stray byte can't either.
RUN printf 'display_errors=Off\nlog_errors=On\nerror_log=/dev/stderr\noutput_buffering=4096\n' > /usr/local/etc/php/conf.d/zz-khetha.ini

WORKDIR /var/www/html
COPY . .

# Render sets $PORT at runtime; default to 10000 for local `docker run`.
ENV PORT=10000
EXPOSE 10000

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT} -t /var/www/html"]
