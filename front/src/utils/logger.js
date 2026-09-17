const LOG_LEVELS = { debug: 0, info: 1, warn: 2, error: 3 };

function getLevel() {
  return LOG_LEVELS[import.meta.env.VITE_LOG_LEVEL || 'warn'];
}

function formatTag(source) {
  return `[${source}]`;
}

export function createLogger(source) {
  const tag = formatTag(source);

  return {
    debug(message, ...args) {
      if (getLevel() <= LOG_LEVELS.debug) {
        console.debug(tag, message, ...args);
      }
    },
    info(message, ...args) {
      if (getLevel() <= LOG_LEVELS.info) {
        console.info(tag, message, ...args);
      }
    },
    warn(message, ...args) {
      if (getLevel() <= LOG_LEVELS.warn) {
        console.warn(tag, message, ...args);
      }
    },
    error(message, ...args) {
      if (getLevel() <= LOG_LEVELS.error) {
        console.error(tag, message, ...args);
      }
    }
  };
}
