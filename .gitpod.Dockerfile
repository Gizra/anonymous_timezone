FROM gitpod/workspace-full

RUN sudo apt-get -qq update

# Install ddev
RUN brew update && brew install drud/ddev/ddev && mkcert -install
