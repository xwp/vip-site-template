FROM golang:1.19-alpine

# Set the version tag to build.
ENV MKCERT_VERSION "v1.4.1"

RUN apk add --no-cache git

RUN git clone https://github.com/FiloSottile/mkcert /go/mkcert \
	&& cd /go/mkcert \
	&& git checkout "tags/$MKCERT_VERSION" -b "build/$MKCERT_VERSION" \
	&& go build -ldflags "-X main.Version=$MKCERT_VERSION" -o /bin/mkcert

WORKDIR /root/.local/share/mkcert
