clean:
	rm -rf build

build:
	mkdir build
	ppm --compile="botsrc" --directory="build"

install:
	ppm --fix-conflict --no-prompt --install="build/net.intellivoid.spam_protection_bot.ppm"

run:
	ppm --main="net.intellivoid.spam_protection_bot" --version="latest"