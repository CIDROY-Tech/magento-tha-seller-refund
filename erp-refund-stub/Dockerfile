# NODE_BASE_IMAGE is passed (digest-pinned) by build/bake.sh; defaults to the
# floating tag for a standalone `docker build` in this directory.
ARG NODE_BASE_IMAGE=node:20-alpine
FROM ${NODE_BASE_IMAGE}

WORKDIR /app

COPY package.json ./
COPY server.js ./

RUN mkdir -p /data && chown -R node:node /data

USER node

ENV PORT=8081
ENV STATE_DIR=/data

EXPOSE 8081
VOLUME ["/data"]

HEALTHCHECK --interval=10s --timeout=3s --start-period=5s --retries=5 \
  CMD node -e "fetch('http://127.0.0.1:'+(process.env.PORT||8081)+'/health').then(r=>process.exit(r.status===200?0:1)).catch(()=>process.exit(1))"

CMD ["node", "server.js"]
