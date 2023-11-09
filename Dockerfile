FROM wyveo/nginx-php-fpm:php81
WORKDIR /usr/share/nginx
RUN rm -rf /usr/share/nginx
RUN ln -s public html

