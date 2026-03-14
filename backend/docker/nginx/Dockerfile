# Stage 1 : Build frontend
FROM node:22-alpine AS frontend-build

ARG VITE_GOOGLE_CLIENT_ID
ENV VITE_GOOGLE_CLIENT_ID=${VITE_GOOGLE_CLIENT_ID}

WORKDIR /app

COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend/ .
RUN npm run build

# Stage 2 : Nginx (non-root, port 8080)
FROM nginxinc/nginx-unprivileged:alpine

COPY backend/docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY backend/docker/nginx/security-headers.conf /etc/nginx/conf.d/security-headers.conf
COPY --from=frontend-build /app/dist /usr/share/nginx/html

EXPOSE 8080
