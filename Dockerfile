FROM php:8.2-apache

# Install ekstensi PHP yang dibutuhkan (mysqli buat database, curl buat panggil API eksternal)
RUN docker-php-ext-install mysqli curl

# Aktifkan mod_rewrite (opsional tapi umum dipakai)
RUN a2enmod rewrite

# Copy semua file project ke folder web server
COPY . /var/www/html/

# Railway kasih PORT lewat environment variable, arahkan Apache ke situ
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf
ENV PORT=80
EXPOSE 80

CMD sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf && \
    sed -i "s/:80/:${PORT}/g" /etc/apache2/sites-available/000-default.conf && \
    apache2-foreground
