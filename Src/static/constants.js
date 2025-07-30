// constants.js
export const STORAGE_KEYS = {
  OPTIONS_BOX_STATE: 'optionsBoxState',
  FEED_FILTER: 'feedFilter', 
  WORKFLOW_LIMITER: 'workflowLimiter',
  WORKFLOW_LIMIT_VALUE: 'workflowLimitValue',
  COLLAPSIBLE_SECTIONS: 'collapsibleSections',
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
  QUEUES: "api/v1/queues",
  DOMAINS: "api/v1/domains",
  GITHUB: "api/v1/github",
  HEALTHCHECKS: "api/v1/healthchecksio",
  UPTIMEROBOT: "api/v1/uptimerobot",
  WIREGUARD: "api/v1/wireguard",
  POSTMAN: "api/v1/postman",
  WEBHOOKS: "api/v1/webhooks"
};

export const CHART_OPTIONS = {
  table: {
    legend: { position: "none" },
    allowHtml: true,
    showRowNumber: true,
    width: "100%",
    height: "100%",
    background: {
      fill: '#000000',
      opacity: .05,
    }
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