clean:
	# Removes the build directory
	rm -rf build

update:
	# Updates the package.json file
	ppm --generate-package="botsrc"

build:
	# Compiles the package
	mkdir build
	ppm --compile="botsrc" --directory="build"

install:
	# Installs the compiled package to the system
	ppm --fix-conflict --no-prompt --install="build/net.intellivoid.spam_protection_bot.ppm" --branch="production"

install_fast:
	# Installs the compiled package to the system
	ppm --fix-conflict --no-prompt --skip-dependencies --install="build/net.intellivoid.spam_protection_bot.ppm" --branch="production"

run:
	# Runs the bot
	ppm --main="net.intellivoid.spam_protection_bot" --version="latest"

stop:
	# Stops the main execution point
	pkill -f 'main=net.intellivoid.spam_protection_bot'

stop_workers:
	# Stops the sub-workers created by BackgroundWorker
	pkill -f 'worker-name=PublicServerchanBot'

debug:
	# Starts the bot, kills all the workers and focuses on one worker in STDOUT
	# Run with -i to ignore possible errors.
	make stop
	screen -dm bash -c 'ppm --main="net.intellivoid.spam_protection_bot" --version="latest"'
	sleep 3
	make stop_workers
	php botsrc/worker.php