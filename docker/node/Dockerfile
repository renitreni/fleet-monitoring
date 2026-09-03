FROM node:20-alpine

WORKDIR /var/www

# Copy entrypoint script
COPY docker/node/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Copy package files first for layer caching
COPY car-maintenance/package.json car-maintenance/package-lock.json* ./
RUN npm install

# Copy application source for production builds
COPY car-maintenance/ .

EXPOSE 5173

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["npm", "run", "dev", "--", "--host"]

