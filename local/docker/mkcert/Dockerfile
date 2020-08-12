FROM golang:1.15-alpine

RUN apk add --no-cache git

RUN cd /go \
	&& go get -u github.com/FiloSottile/mkcert \
	&& cd src/github.com/FiloSottile/mkcert \
	&& go build -ldflags "-X main.Version=$(git describe --tags)" -o /bin/mkcert

WORKDIR /root/.local/share/mkcert
