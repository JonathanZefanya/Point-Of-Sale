FROM node:22-alpine

RUN apk add --no-cache netcat-openbsd

WORKDIR /app

COPY pos-web/package.json pos-web/package-lock.json ./
RUN npm ci

COPY docker/web/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 5173
ENTRYPOINT ["entrypoint.sh"]
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--port", "5173"]
