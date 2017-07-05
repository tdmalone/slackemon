# Slackémon
[![Follow Slackémon on Twitter!](https://img.shields.io/twitter/follow/slackemon.svg?style=social&label=Follow)](https://twitter.com/slackemon)

[![Latest Release](https://img.shields.io/github/release/tdmalone/slackemon/all.svg)](https://github.com/tdmalone/slackemon/releases)
[![Join us on Slack!](https://img.shields.io/badge/chat%2fplay-on%20slack-e01563.svg)](https://join.slack.com/playslackemon/shared_invite/MTk4Nzc5MTc0MDk2LTE0OTc3NTAwODgtNmU4MDZkZDU0MA)  
[![Linux build status](https://travis-ci.org/tdmalone/slackemon.svg?branch=master)](https://travis-ci.org/tdmalone/slackemon)
[![Windows build status](https://img.shields.io/appveyor/ci/TimMalone/slackemon.svg)](https://ci.appveyor.com/project/TimMalone/slackemon)
[![Docker build status](https://img.shields.io/docker/build/tdmalone/slackemon.svg)](https://hub.docker.com/r/tdmalone/slackemon/builds/)
[![Codacy code quality grade](https://img.shields.io/codacy/grade/229c6c8928db485fa87f7648d150dbd8/master.svg)](https://www.codacy.com/app/tdmalone/slackemon/dashboard)
[![Coverage status](https://img.shields.io/codecov/c/github/tdmalone/slackemon/master.svg)](https://codecov.io/gh/tdmalone/slackemon/branch/master)

Inspired by Pokémon Go, now you can catch and battle Pokémon with your teammates on Slack!

**This program is very much a WORK IN PROGRESS, and should be considered very *ALPHA*. If you have any questions you're welcome to log an issue, but please be aware that code may be messy/incomplete, and some things may not work. In addition, not all mechanics are implemented yet. We're getting there!**

[![Deploy to Heroku](https://www.herokucdn.com/deploy/button.svg)](https://heroku.com/deploy)  
[Full Heroku deployment instructions](https://github.com/tdmalone/slackemon/wiki/Installing-on-Heroku)  
Scroll down for more setup options

## Screenshots

<a href="https://github.com/tdmalone/slackemon/blob/5109c1f231ac54d29d0aa26094342aaeff7dc51a/media/screenshots/spawn.png"><img src="https://cdn.rawgit.com/tdmalone/slackemon/5109c1f231ac54d29d0aa26094342aaeff7dc51a/media/screenshots/spawn.png" alt="Pokemon spawn" height="250"></a> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <a href="https://github.com/tdmalone/slackemon/blob/5109c1f231ac54d29d0aa26094342aaeff7dc51a/media/screenshots/wild-battle.png"><img src="https://cdn.rawgit.com/tdmalone/slackemon/5109c1f231ac54d29d0aa26094342aaeff7dc51a/media/screenshots/wild-battle.png" alt="Achievements screen" height="250"></a>

[More screenshots](https://github.com/tdmalone/slackemon/tree/master/media/screenshots)

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

* A web environment running PHP7
* Access to a Slack organisation (if a free organisation, installing this will take up 1 of your 10 integrations)
* **Optional:** An API key for [OpenWeatherMap](http://openweathermap.org), to enable weather features based on real-world weather
* **Optional:** An [AWS S3](https://aws.amazon.com/s3/) bucket for better performance when caching image assets

## Setup

Setup of Slackémon is _not_ quick. This may be worked on further in the future. For now, there are quite a few steps!

1. Download/clone/etc. the contents of this repository, and put it on a web server somewhere that runs PHP7.
    * [Download ZIP](https://github.com/tdmalone/slackemon/archive/master.zip) or clone with Git: `git clone https://github.com/tdmalone/slackemon.git`
        * You may need to install depdendencies as well by running `composer install`. If you don't have Composer, [get it here](https://getcomposer.org/download/) first. If you're not going to be developing Slackémon, you can speed up the install of depdendencies by instead running `composer install --no-dev`.
    * Install with Composer: `composer require tdmalone/slackemon`
    * [Deploy with Docker](https://github.com/tdmalone/slackemon/wiki/Installing-with-Docker)
    * [Deploy with Heroku](https://github.com/tdmalone/slackemon/wiki/Installing-on-Heroku)
1. Log in to your Slack team, and visit https://api.slack.com/apps?new_app=1 to create a new App. You can call it whatever you like, but 'Slackémon' usually works best! You can then proceed to set up the app features through Slack's interface.
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
    1. Click Save Changes, then at the top of the page, click the button to install the app to your team, giving it the permissions it asks for. Make a note of your OAuth Access Token.
        * _At this point, if you are on a free Slack team, Slackémon will take up one of your 10 available integrations. If you have already used your 10 integrations, you'll need to completely remove one at `https://YOUR-DOMAIN.slack.com/apps/manage` before you can add Slackémon - or alternatively, upgrade to a paid Slack plan._
1. Head back to the 'Basic Information' page for your app, and scroll down to your app's credentials section. Make a note of the _Verification Token_.
   * You may also like to set up an icon for your app to make messages from it look slightly nicer - [this Pokéball](https://raw.githubusercontent.com/tdmalone/slackemon/42850c6e179/media/pokeball.png) makes a good icon!
1. Set up your environment variables, either by copying `.env.example` to `.env` (development mode only - see instructions in the file), or setting the variables within your environment (recommended). Either way, see [`.env.example`](https://github.com/tdmalone/slackemon/blob/master/.env.example) for instructions on the variables to set.
1. In your system's crontab (eg. `crontab -e` on a Linux machine, or find the Cron option in your hosting control panel), set up `cron.php` to run every minute, sending through the cron token you created earlier.
    * If you're invoking via the command line, you can use eg. `php /path/to/cron.php --token=XXXXXXXXXXXXXXXXXXXX`
    * If you're invoking via a GET request, you can use eg. `http://example.com/slackemon/cron.php?token=XXXXXXXXXXXXXXXXXXXX`
    * If you don't have access to cron, you can use a service such as [cron-job.org](https://cron-job.org)
1. Time to install custom emoji! There's a lot to install, so we make use of the wonderful [emojipacks](https://github.com/lambtron/emojipacks):
    * Enter `npm install -g emojipacks` at a command line (if you don't have Node.js/NPM installed, [do that first](https://nodejs.org/en/download/))
    * Run `emojipacks -y https://github.com/tdmalone/slackemon/blob/master/etc/emojipack.yml`
    * You will be prompted for your Slack subdomain (eg. `YOUR-SUBDOMAIN.slack.com` - leave out the `slack.com` part when entering it) as well as your username and password for Slack (_neither Slackémon nor emojipacks will have access to your login details; it is simply required because Slack doesn't yet have an API endpoint for uploading emoji_)
    * Wait... for quite awhile! When the script is done, you'll have almost a thousand new custom emoji covering every single Pokémon plus a few more custom emoji used by Slackémon 😃
    * **If you can't install custom emoji in your Slack organisation, ask your team admins, or set `SLACKEMON_ENABLE_CUSTOM_EMOJI` to `false` in your environment variables.**

Well done, it's time to start playing! You should now be able to run `/slackemon` anywhere in your Slack team to start the quick user onboarding process 👍

## Future enhancements

Future enhancement ideas are progressively being added to the [Projects](https://github.com/tdmalone/slackemon/projects) section.

You can generally track what we're working on at moment through the [Milestones](https://github.com/tdmalone/slackemon/milestones).

## User guide

A user guide will progressively be written at [this repo's wiki](https://github.com/tdmalone/slackemon/wiki).

## Acknowledgements

Slackémon was first and foremost inspired by [Pokémon Go](http://www.pokemongo.com/). It borrows a few mechanics from Pokémon Go (mainly for simplicity), but as time goes on the aim is to be more true to the original Pokémon games wherever possible.

The idea of doing this on Slack came from Robert Vinluan's [bot for having Pokemon battles in Slack](https://github.com/rvinluan/slack-pokemon). Inspiration and some battle move data has also been gleaned from [Pokémon Showdown](http://pokemonshowdown.com/).

Thank you to those who have done the hard yards in bringing together Pokémon sprites, particularly [PokeCSS](https://github.com/metaunicorn/pokecss-media).

Thank you to my co-worker [Julian](http://github.com/juz501), who has been my main playmate in testing (and competing with!) this on our company Slack organisation at [Chromatix](https://www.chromatix.com.au), and to [Alessandro Pezzè](https://github.com/Naramsim) for being the first 'stranger contributor' to jump on board the project :). You can see the full list of contributors in [`CONTRIBUTORS.md`](https://github.com/tdmalone/slackemon/blob/master/CONTRIBUTORS.md).

Thanks to [Slack](http://slack.com) for maintaining a well-documented, open API and inviting collaboration on their platform.

Last but not least, this project would never have happened without the extensive work undertaken by Paul Hallet at [Pokéapi](http://pokeapi.co). Working on Slackémon has been fun rather than painful, thanks to the rich collection of readibly accessible data that Paul and his team have collected and structured. Thank you!

## License

[![License](https://poser.pugx.org/tdmalone/slackemon/license)](https://github.com/tdmalone/slackemon/blob/master/LICENSE)
[![FOSSA Status](https://app.fossa.io/api/projects/git%2Bhttps%3A%2F%2Fgithub.com%2Ftdmalone%2Fslackemon.svg?type=shield)](https://app.fossa.io/projects/git%2Bhttps%3A%2F%2Fgithub.com%2Ftdmalone%2Fslackemon?ref=badge_shield)

Slackémon - Catch and battle Pokémon with your teammates on Slack  
Copyright (C) 2016-2017, [Tim Malone](https://github.com/tdmalone) and contributors.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
[GNU General Public License](https://github.com/tdmalone/slackemon/blob/master/LICENSE) for more details.
