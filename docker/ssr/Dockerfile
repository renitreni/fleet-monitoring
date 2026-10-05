FROM node:22-alpine

WORKDIR /var/www

COPY car-maintenance/package.json car-maintenance/package-lock.json ./
RUN npm ci --omit=dev
COPY car-maintenance/bootstrap/ssr ./bootstrap/ssr

USER node
CMD ["node", "bootstrap/ssr/ssr.js"]
