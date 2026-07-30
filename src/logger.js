// Minimal structured logger. Keeps output greppable in any hosting dashboard
// without pulling in a logging framework.
function log(level, message, meta) {
  const entry = {
    time: new Date().toISOString(),
    level,
    message,
    ...(meta ? { ...meta } : {}),
  };
  const line = JSON.stringify(entry);
  if (level === 'error') {
    console.error(line);
  } else {
    console.log(line);
  }
}

export const logger = {
  info: (message, meta) => log('info', message, meta),
  warn: (message, meta) => log('warn', message, meta),
  error: (message, meta) => log('error', message, meta),
};
