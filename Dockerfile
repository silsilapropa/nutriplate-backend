FROM php:8.2-apache

# Install library sistem yang dibutuhkan (libcurl buat ekstensi curl PHP)
RUN apt-get update && apt-get install -y libcurl4-openssl-dev pkg-config && rm -rf /var/lib/apt/lists/*

# Install ekstensi PHP yang dibutuhkan (mysqli buat database, curl buat panggil API eksternal)
RUN docker-php-ext-install mysqli curl

# Paksa hapus konfigurasi MPM yang konflik, sisakan cuma mpm_prefork
RUN rm -f /etc/apache2/mods-enabled/mpm_event.conf \
           /etc/apache2/mods-enabled/mpm_event.load \
           /etc/apache2/mods-enabled/mpm_worker.conf \
           /etc/apache2/mods-enabled/mpm_worker.load \
    && ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load \
    && ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf

# Copy semua file project ke folder web server
COPY . /var/www/html/

ENV PORT=80
EXPOSE 80

CMD sh -c "sed -i \"s/Listen 80/Listen \$PORT/g\" /etc/apache2/ports.conf && sed -i \"s/:80/:\$PORT/g\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"
