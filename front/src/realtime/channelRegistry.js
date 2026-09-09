// PLAN.md Stage 5.1 — reference-counted channel registry built on top of the echo.js
// singleton. Multiple consumers (e.g. two monitors on the same sensor) can each listen on
// the same channel/event without stepping on each other: the underlying Echo/Pusher channel
// is only torn down (leaveChannel) when the LAST consumer releases its listener, instead of
// every consumer's unsubscribe tearing down the whole channel for everyone else.
import { getEcho } from './echo';

const refCounts = new Map(); // channel key -> number of active listeners

function channelKey(channelName, { privateChannel = false } = {}) {
  return `${privateChannel ? 'private' : 'public'}:${channelName}`;
}

function leaveChannelName(channelName, { privateChannel = false } = {}) {
  return privateChannel ? `private-${channelName}` : channelName;
}

/**
 * Subscribe `callback` to `eventName` on `channelName`, sharing the underlying Echo channel
 * with any other current listener on the same channel. Returns a `release()` function that
 * must be called exactly once when this particular consumer no longer needs the event; the
 * channel itself is only left when the last consumer releases.
 */
export function listenOnChannel(channelName, eventName, callback, { privateChannel = false } = {}) {
  const echo = getEcho();

  if (!echo) {
    return null;
  }

  const channel = privateChannel ? echo.private?.(channelName) : echo.channel(channelName);
  if (!channel) {
    return null;
  }

  channel.listen(eventName, callback);
  const key = channelKey(channelName, { privateChannel });
  refCounts.set(key, (refCounts.get(key) || 0) + 1);

  let released = false;

  return function release() {
    if (released) {
      return;
    }
    released = true;

    channel.stopListening(eventName, callback);
    const remaining = (refCounts.get(key) || 1) - 1;

    if (remaining <= 0) {
      refCounts.delete(key);
      // ponytail: assumes the echo instance captured at listen-time is still the live one.
      // Holds because disconnectEcho() is only ever called after consumers have already
      // released (see reconnectAlerts/auth-change flow in useAlertsRealtime.js) — revisit if
      // a future caller disconnects Echo while channels are still ref-counted.
      echo.leaveChannel(leaveChannelName(channelName, { privateChannel }));
    } else {
      refCounts.set(key, remaining);
    }
  };
}

export function getChannelRefCount(channelName, { privateChannel = false } = {}) {
  return refCounts.get(channelKey(channelName, { privateChannel })) || 0;
}
