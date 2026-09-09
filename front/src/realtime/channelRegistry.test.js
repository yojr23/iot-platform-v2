import { beforeEach, describe, expect, it, vi } from 'vitest';

const echoMock = vi.hoisted(() => {
  const publicChannel = {
    listen: vi.fn(),
    stopListening: vi.fn(),
  };
  const privateChannel = {
    listen: vi.fn(),
    stopListening: vi.fn(),
  };
  const echo = {
    channel: vi.fn(() => publicChannel),
    private: vi.fn(() => privateChannel),
    leaveChannel: vi.fn(),
  };

  return { echo, publicChannel, privateChannel };
});

vi.mock('./echo', () => ({
  getEcho: () => echoMock.echo,
}));

describe('channel registry channel type ownership', () => {
  beforeEach(() => {
    vi.resetModules();
    echoMock.echo.channel.mockClear();
    echoMock.echo.private.mockClear();
    echoMock.echo.leaveChannel.mockClear();
    echoMock.publicChannel.listen.mockClear();
    echoMock.publicChannel.stopListening.mockClear();
    echoMock.privateChannel.listen.mockClear();
    echoMock.privateChannel.stopListening.mockClear();
  });

  it('uses a public Echo channel by default', async () => {
    const { listenOnChannel, getChannelRefCount } = await import('./channelRegistry');
    const callback = vi.fn();

    const release = listenOnChannel('sensor.7', 'NewSensorReading', callback);

    expect(echoMock.echo.channel).toHaveBeenCalledWith('sensor.7');
    expect(echoMock.echo.private).not.toHaveBeenCalled();
    expect(echoMock.publicChannel.listen).toHaveBeenCalledWith('NewSensorReading', callback);
    expect(getChannelRefCount('sensor.7')).toBe(1);
    release();
    expect(echoMock.echo.leaveChannel).toHaveBeenCalledWith('sensor.7');
  });

  it('keeps private and public channel ref counts and release names separate', async () => {
    const { listenOnChannel, getChannelRefCount } = await import('./channelRegistry');
    const callback = vi.fn();

    const releasePrivate = listenOnChannel('sensor.7', 'NewSensorReading', callback, { privateChannel: true });
    const releasePublic = listenOnChannel('sensor.7', 'NewSensorReading', callback);

    expect(echoMock.echo.private).toHaveBeenCalledWith('sensor.7');
    expect(echoMock.echo.channel).toHaveBeenCalledWith('sensor.7');
    expect(echoMock.privateChannel.listen).toHaveBeenCalledWith('NewSensorReading', callback);
    expect(getChannelRefCount('sensor.7', { privateChannel: true })).toBe(1);
    expect(getChannelRefCount('sensor.7')).toBe(1);

    releasePrivate();
    expect(echoMock.echo.leaveChannel).toHaveBeenCalledWith('private-sensor.7');
    expect(getChannelRefCount('sensor.7', { privateChannel: true })).toBe(0);
    expect(getChannelRefCount('sensor.7')).toBe(1);

    releasePublic();
    expect(echoMock.echo.leaveChannel).toHaveBeenCalledWith('sensor.7');
    expect(getChannelRefCount('sensor.7')).toBe(0);
  });
});
