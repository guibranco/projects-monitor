// constants.js
export const STORAGE_KEYS = {
  OPTIONS_BOX_STATE: 'optionsBoxState',
  FEED_FILTER: 'feedFilter',
  COLLAPSIBLE_SECTIONS: 'collapsibleSections',
  TABLE_PAGE_SIZES: 'tablePageSizes',
};

export const VALID_STATES = {
  OPEN: "open",
  COLLAPSED: "collapsed"
};

export const FEED_FILTERS = {
  ALL: "all",
  MINE: "mine"
};

export const API_ENDPOINTS = {
  APPVEYOR: "api/v1/appveyor",
  CPANEL: "api/v1/cpanel",
  CPANEL_DELETE: "api/v1/cpanel/delete",
  MESSAGES: "api/v1/messages",
  MESSAGES_DELETE: "api/v1/messages/delete",
  MESSAGES_TRUNCATE: "api/v1/messages/truncate",
  MESSAGES_DETAILS: "api/v1/messages/details",
  MESSAGES_DELETE_SEQUENCE: "api/v1/messages/delete-sequence",
  MESSAGES_DELETE_GROUP: "api/v1/messages/delete-group",
  QUEUES: "api/v1/queues",
  QUEUES_PURGE: "api/v1/queues/purge",
  DOMAINS: "api/v1/domains",
  ERRORS: "api/v1/errors",
  ERRORS_TRUNCATE: "api/v1/errors/truncate",
  ERRORS_DELETE_PATH: "api/v1/errors/delete-path",
  GITHUB: "api/v1/github",
  HEALTHCHECKS: "api/v1/healthchecksio",
  UPTIMEROBOT: "api/v1/uptimerobot",
  WIREGUARD: "api/v1/wireguard",
  POSTMAN: "api/v1/postman",
  WEBHOOKS: "api/v1/webhooks",
  WEBHOOKS_STATISTICS: "api/v1/webhooks-statistics",
  WEBHOOKS_PR_PROCESSING: "api/v1/webhooks-pull-requests-processing"
};

export const CHART_OPTIONS = {
  table: {
    allowHtml: true,
    showRowNumber: true,
    sort: false,
    pagination: true,
    search: false,
    width: "100%",
    height: "100%",
  },

  gauge: {
    width: "100%",
    height: "100%",
    min: 0,
    max: 1000,
    greenFrom: 0,
    greenTo: 250,
    yellowFrom: 250,
    yellowTo: 500,
    redFrom: 500,
    redTo: 1000
  }
};

export const GITHUB_URLS = {
  STATS: "https://github-readme-stats-guibranco.vercel.app/api?username=guibranco&line_height=28&card_width=490&hide_title=true&hide_border=true&show_icons=true&theme=chartreuse-dark&icon_color=7FFF00&include_all_commits=true&count_private=true&show=reviews,discussions_started&count_private=true",
  STREAK: "https://github-readme-streak-stats-guibranco.vercel.app/?user=guibranco&theme=github-green-purple&fire=FF6600",
  WAKATIME: "https://wakatime.com/badge/user/6be975b7-7258-4475-bc73-9c0fc554430e.svg?style=for-the-badge"
};