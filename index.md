---
title: Deck for Nextcloud
layout: default
---

Deck is a kanban style organization tool aimed at personal planning and project organization for teams integrated with Nextcloud.

![Deck - Manage cards on your board](https://download.bitgrid.net/nextcloud/deck/screenshots/Deck_Board.png)

![Deck - Add more details to cards](https://download.bitgrid.net/nextcloud/deck/screenshots/Deck_Details.png)

- Add your tasks to cards and put them in order
- Write down additional notes in markdown
- Assign labels for even better organization
- Share with your team, friends or family
- Get your project organized


# Installation

This app is supposed to work on Nextcloud version 11 or later.

### Install latest release

You can download and install the latest release from the [Nextcloud app store](https://apps.nextcloud.com/apps/deck). The changelog can be found [here](https://github.com/nextcloud/deck/blob/master/CHANGELOG.md).

### Install from git

If you want to run the latest development version from git source, you need to clone the repo to your apps folder:

```
git clone https://github.com/nextcloud/deck.git
cd deck
make install-deps
make
```

Please make sure you have installed the following dependencies: `make, which, tar, npm, curl`

### Install the nightly builds

Instead of setting everything up manually, you can just [download the nightly builds](https://download.bitgrid.net/nextcloud/deck/nightly/) instead. These builds are updated every 24 hours, and are pre-configured with all the needed dependencies.

# Donations

There are multiple possibilities to show us your support by donating:

- [Liberapay](https://liberapay.com/Deck/)
- [PayPal](https://www.paypal.me/JuliusHaertl)
- [Bountysource](https://www.bountysource.com/teams/nextcloud-deck)
