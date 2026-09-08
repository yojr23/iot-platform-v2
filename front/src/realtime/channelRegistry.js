// PLAN.md Stage 5.1 — reference-counted channel registry built on top of the echo.js
// singleton. Multiple consumers (e.g. two monitors on the same sensor) can each listen on
// the same channel/event without stepping on each other: the underlying Echo/Pusher channel
// is only torn down (leaveChannel) when the LAST consumer releases its listener, instead of
// every consumer's unsubscribe tearing down the whole channel for everyone else.
import { getEcho } from './echo';

const refCounts = new Map(); // channelName -> number of active listeners

/**
 * Subscribe `callback` to `eventName` on `channelName`, sharing the underlying Echo channel
 * with any other current listener on the same channel. Returns a `release()` function that
 * must be called exactly once when this particular consumer no longer needs the event; the
 * channel itself is only left when the last consumer releases.
 */
export function listenOnChannel(channelName, eventName, callback) {
  const echo = getEcho();

  if (!echo) {
    return null;
  }

  const channel = echo.channel(channelName);
  channel.listen(eventName, callback);
  refCounts.set(channelName, (refCounts.get(channelName) || 0) + 1);

  let released = false;

  return function release() {
    if (released) {
      return;
    }
    released = true;

    channel.stopListening(eventName, callback);
    const remaining = (refCounts.get(channelName) || 1) - 1;

    if (remaining <= 0) {
      refCounts.delete(channelName);
      // ponytail: assumes the echo instance captured at listen-time is still the live one.
      // Holds because disconnectEcho() is only ever called after consumers have already
      // released (see reconnectAlerts/auth-change flow in useAlertsRealtime.js) — revisit if
      // a future caller disconnects Echo while channels are still ref-counted.
      echo.leaveChannel(channelName);
    } else {
      refCounts.set(channelName, remaining);
    }
  };
}

export function getChannelRefCount(channelName) {
  return refCounts.get(channelName) || 0;
}
