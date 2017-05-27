# Slackémon

[![Join the chat at https://gitter.im/slackemon/Lobby](https://badges.gitter.im/slackemon/Lobby.svg)](https://gitter.im/slackemon/Lobby?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

[![Deploy to Heroku](https://www.herokucdn.com/deploy/button.svg)](https://heroku.com/deploy) (Heroku deployment is IN TESTING)

Inspired by Pokémon Go, now you can catch and battle Pokémon with your teammates on Slack!

**This program is very much a WORK IN PROGRESS, and should be considered very *ALPHA*. If you have any questions you're welcome to log an issue, but please be aware that code may be messy/incomplete, and some things may not work. In addition, not all mechanics are implemented yet.**

## Screenshots

<a href="https://github.com/tdmalone/slackemon/blob/master/_images/screenshots/spawn.png"><img src="https://raw.githubusercontent.com/tdmalone/slackemon/master/_images/screenshots/spawn.png" alt="Pokemon spawn" height="250"></a> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <a href="https://github.com/tdmalone/slackemon/blob/master/_images/screenshots/wild-battle.png"><img src="https://raw.githubusercontent.com/tdmalone/slackemon/master/_images/screenshots/wild-battle.png" alt="Achievements screen" height="250"></a>

<a href="https://github.com/tdmalone/slackemon/blob/master/_images/screenshots/pokemon-menu.png"><img src="https://raw.githubusercontent.com/tdmalone/slackemon/master/_images/screenshots/pokemon-menu.png" alt="Achievements screen" height="430"></a> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <a href="https://github.com/tdmalone/slackemon/blob/master/_images/screenshots/achievements.png"><img src="https://raw.githubusercontent.com/tdmalone/slackemon/master/_images/screenshots/achievements.png" alt="Achievements screen" height="430"></a>

More screenshots can be found in the `_images/screenshots/` folder.

## Features

* Catch randomly spawned wild Pokémon and build your collection
* 'Travel' to different regions in the Pokémon world to find different wild Pokémon
* Battle wild Pokémon to make them easier to catch, and to level up your own Pokémon
* Battle your teammates to see who is the strongest!
* Compete with your teammates to see who can fill their Pokédex first
* Pick up randomly spawned items 'found on the ground': teach your Pokémon new moves, and evolve some Pokémon!
* Support for evolution based on level, happiness, time of day, item use, and known move/move type
* Tools to manage your Pokémon collection
* Live weather integration for a higher chance of, for example, water Pokémon during rain
* Add custom logic to control when legendary Pokémon can spawn
* Implements IVs, EVs, happiness, natures and growth rates
* Implements damage moves with PP, damage class, STAB, type effectiveness, recoil and drain
* Fainted, damaged and low-PP Pokémon auto-heal after an amount of time
* Respects users' Slack DND settings, so no-one wakes up to hundreds of missed spawns
* Includes a user-specific 'offline mode' so the game can be turned off when the interruptions are not wanted

## Requirements

* A web server running PHP7
* Access to a Slack organisation (if a free organisation, installing this will take up 1 of your 10 integrations)
* **Optional:** An API key for OpenWeatherMap, to enable weather features based on real-world weather
* **Optional:** An AWS S3 bucket for better performance when caching image assets

## Setup

Setup of Slackémon is _not_ quick. This may be worked on further in the future. For now, there are quite a few steps!

1. Download/clone/etc. the contents of this repository, and put it on a web server somewhere that runs PHP7.
    * Alternatively you can [deploy it locally with Docker](https://github.com/tdmalone/slackemon/wiki/Installing-with-Docker)
    * Alternatively you can [deploy directly to Heroku](https://heroku.com/deploy)
1. Log in to your Slack team, and visit https://api.slack.com/apps?new_app=1 to create a new App. You can call it whatever you like, but 'Slackémon' usually works best!
    1. From your App control page, take note of your App's 'token'
1. Set up Slack's Interactive Messages
    1. From your App control page, under Features in the sidebar, click Interactive Messages
    1. For the Request URL and Options URL, enter the address where you are hosting Slackémon (eg. http://example.com/slackemon)
1. Set up Slack's Slash Commands
    1. From your App control page, under Features in the sidebar, click Slash Commands
    1. Create a new command, naming it `/slackemon`. Set the Request URL to the same URL you used above.
    1. Enter any description you like - this is what will show to your team when they start typing `/slacke....`. Something like 'Catch Pokémon and battle your teammates!' usually works well.
    1. Leave the 'Escape channels...' checkbox unticked, and click Save.
1. Set up permissions and install the app to your team
    1. From your App control page, under Features in the sidebar, click OAuth & Permissions
    1. Scroll down and add the following permission scopes:
        * dnd:read
        * chat:write:bot
        * users.profile:read
        * users:read
    1. At the top of the page, click the button to install the app to your team, giving it the permissions it asks for. Make a note of your OAuth Access Token.
1. Set up your local configuration file
    1. Copy `config.sample.php` to `config.php`
    1. Put your Slack App's token in the marked space, and add your team ID too (your team ID is 9 characters, starts with T, and can be found in the URL of ....)
    1. In the `GLOBAL_MAINTAINERS` constant, enter your team ID and _your_ Slack user ID as the maintainer (you can find your user ID at ...)
    1. For the Slack key, enter the oAuth token you received when setting up the Slack app on your team
    1. **Optional**: To enable real-world weather integration, sign up for a free account at [OpenWeatherMap](http://openweathermap.org) and place your API key in as the Weather key
    1. Make up a random token to use to authenticate the cron runner - you'll need this again soon
    1. Enter your user ID and team ID in the required place for each of the cron runs (if you have the capacity to set up a service user, it can be a good idea to use that here instead)
    1. Adjust the cron schedule if you like:
        * Battle updates *must* happen every minute - changing this can cause turn reminders to be missed during PVP battles
        * The `maybe-spawn` command is designed to run every minute, as it chooses whether to spawn or not based on chance. However, you can limit the hours/days etc. as you desire, for example to cause spawns to only occur outside business hours if you wish your team to focus on their work.
        * Happiness updates are designed to happen once a day, but nothing bad will happen if you do it more often: happiness will just grow quicker!
    1. Set the `INBOUND_URL` to the public URL where your instance of Slackémon can be accessed
    1. Configure your image caching options, if you don't wish to cache locally
        * Using AWS is recommended, but if you have good hosting you should be fine with the local cache
        * Please do not disable the cache except for testing, otherwise you will put an unfair burden on those who host the images
    1. Set your timezone, lat/lon for real-world weather, monetary locale (the latter is used for displaying the value of items)
1. In your system's crontab (eg. `crontab -e` on a Linux machine, or find the Cron option in your hosting control panel), set up `cron.php` to run every minute, sending through the cron token you created earlier.
    * If you're invoking via the command line, you can use eg. `php /path/to/cron.php --token=XXXXXXXXXXXXXXXXXXXX`
    * If you're invoking via a GET request, you can use eg. `http://example.com/slackemon/cron.php?token=XXXXXXXXXXXXXXXXXXXX`
1. TODO: Add steps for installing custom emoji, including Pokémon & type emoji
1. TODO: Add steps for cloning [PokeCSS](https://github.com/metaunicorn/pokecss-media) to your install

## Future enhancements

Future enhancement ideas are progressively being added to the [Projects](https://github.com/tdmalone/slackemon/projects) section.

## User guide

A user guide will progressively be written at [this repo's wiki](https://github.com/tdmalone/slackemon/wiki).

## Acknowledgements

Slackémon was first and foremost inspired by [Pokémon Go](http://www.pokemongo.com/). It borrows a few mechanics from Pokémon Go (mainly for simplicity), but as time goes on the aim is to be more true to the original Pokémon games wherever possible.

The idea of doing this on Slack came from Robert Vinluan's [bot for having Pokemon battles in Slack](https://github.com/rvinluan/slack-pokemon). Inspiration has also been gleaned from [Pokémon Showdown](http://pokemonshowdown.com/) - and I will no doubt be making use of their battle data to further expand the moves Slackémon can deal with!

Thank you to those who have done the hard yards in bringing together Pokémon sprites, particularly [PokeCSS](https://github.com/metaunicorn/pokecss-media).

Thank you to my co-worker [Julian](http://github.com/juz501), who has been my main playmate in testing (and competing with!) this on our company Slack organisation at [Chromatix](https://www.chromatix.com.au).

Thanks to [Slack](http://slack.com) for maintaining a well-documented, open API and inviting collaboration on their platform.

Last but not least, this project would never have happened without the extensive work undertaken by Paul Hallet at [Pokéapi](http://pokeapi.co). Working on Slackémon has been fun rather than painful, thanks to the rich collection of readibly accessible data that Paul and his team have collected and structured. Thank you!

## License

This project is licensed under version 3 of the GNU Public License. See `LICENSE` for full details.
