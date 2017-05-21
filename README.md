# slackemon

Inspired by Pokémon Go, now you can catch and battle Pokémon with your teammates on Slack!

**This program is very much a work in progress, and should be considered very *alpha*. If you have any questions you're welcome to log an issue, but please be aware that code may be messy/incomplete, and some things may not work. In addition, not all mechanics are implemented yet.**

## Screenshots

Coming soon!

## Features

Coming soon!

## Requirements

* A web server running PHP7
* Access to a Slack organisation (if a free organisation, installing this will take up 1 of your 10 integrations)
* **Optional:** An API key for OpenWeatherMap, to enable weather features based on real-world weather
* **Optional:** An AWS S3 bucket for better performance when caching image assets

## Setup

Setup of Slackémon is not quick. This may be worked on further in the future. For now, there are quite a few steps!

1. Download/clone/etc. the contents of this repository, and put it on a web server somewhere that runs PHP7.
1. Log in to your Slack team, and visit https://api.slack.com/apps?new_app=1 to create a new App. You can call it whatever you like, but 'Slackémon' usually works best!
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
1. TODO: Add steps for installing custom Pokémon emoji, including type emoji
1. TODO: Add steps for setting up Slackémon config file
1. TODO: Add steps for setting up cron

## Future enhancements

Coming soon!

## Acknowledgements

Slackémon was first and foremost inspired by [Pokémon Go](http://www.pokemongo.com/). It borrows a few mechanics from Pokémon Go (mainly for simplicity), but as time goes on the aim is to be more true to the original Pokémon games wherever possible.

The idea of doing this on Slack came from Robert Vinluan's [bot for having Pokemon battles in Slack](https://github.com/rvinluan/slack-pokemon). Inspiration has also been gleaned from [Pokémon Showdown](http://pokemonshowdown.com/) - and I will no doubt be making use of their battle data to further expand the moves Slackémon Go can deal with!

Thank you to those who have done the hard yards in bringing together Pokémon sprites, particularly [PokeCSS](https://github.com/metaunicorn/pokecss-media).

Thank you to my co-worker [Julian](http://github.com/juz501), who has been my main playmate in testing (and competing with!) this on our company Slack organisation at [Chromatix](https://www.chromatix.com.au).

Thanks to [Slack](http://slack.com) for maintaining a well-documented, open API and inviting collaboration on their platform.

Last but not least, this project would never have happened without the extensive work undertaken by Paul Hallet with [Pokéapi](http://pokeapi.co). Working on Slackémon has been fun rather than painful, thanks to the rich collection of readibly accessible data that Paul has collected and structured. Thank you!

## License

This project is licensed under version 3 of the GNU Public License. See `LICENSE` for full details.
