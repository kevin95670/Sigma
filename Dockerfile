FROM wordpress:6-php8.3-apache

# msmtp : redirige les e-mails envoyés par PHP/WordPress vers Mailpit (aucun mail réel n'est envoyé)
# mariadb-client : mysqldump/mysql en ligne de commande (export/import de la base)
# git, zip, unzip : requis par Composer
RUN apt-get update && apt-get install -y --no-install-recommends \
        msmtp msmtp-mta mariadb-client git zip unzip \
    && rm -rf /var/lib/apt/lists/*

# Composer (utile si le plugin maison utilise des paquets)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN printf '%s\n' \
    'defaults' \
    'auth off' \
    'tls off' \
    'logfile /var/log/msmtp.log' \
    '' \
    'account mailpit' \
    'host mailpit' \
    'port 1025' \
    'from resto@sigma.local' \
    '' \
    'account default : mailpit' \
    > /etc/msmtprc \
    && chmod 644 /etc/msmtprc

# PHP : utiliser msmtp comme sendmail + réglages de dev
RUN { \
    echo 'sendmail_path = "/usr/bin/msmtp -t"'; \
    echo 'upload_max_filesize = 64M'; \
    echo 'post_max_size = 64M'; \
    echo 'memory_limit = 256M'; \
    } > /usr/local/etc/php/conf.d/sigma-dev.ini

# Apache : permaliens WordPress
RUN a2enmod rewrite