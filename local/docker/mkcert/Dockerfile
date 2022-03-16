FROM golang:1.18-alpine

RUN apk add --no-cache git

RUN cd /go \
	&& git clone https://github.com/FiloSottile/mkcert \
	&& cd mkcert \
	&& go build -ldflags "-X main.Version=$(git describe --tags)" -o /bin/mkcert

WORKDIR /root/.local/share/mkcert
