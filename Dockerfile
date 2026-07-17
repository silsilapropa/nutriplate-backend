FROM php:8.2-apache

# Install library sistem yang dibutuhkan (libcurl buat ekstensi curl PHP)
RUN apt-get update && apt-get install -y libcurl4-openssl-dev pkg-config && rm -rf /var/lib/apt/lists/*

# Install ekstensi PHP yang dibutuhkan (mysqli buat database, curl buat panggil API eksternal)
RUN docker-php-ext-install mysqli curl

# Pastikan cuma 1 modul MPM yang aktif (fix konflik "More than one MPM loaded")
RUN a2dismod mpm_event mpm_worker 2>/dev/null; a2enmod mpm_prefork

# Copy semua file project ke folder web server
COPY . /var/www/html/

ENV PORT=80
EXPOSE 80

CMD sh -c "sed -i \"s/Listen 80/Listen \$PORT/g\" /etc/apache2/ports.conf && sed -i \"s/:80/:\$PORT/g\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"
