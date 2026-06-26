let alertAudioContext = null;
let unlockBound = false;

function getAudioContext() {
  const AudioContextClass = window.AudioContext || window.webkitAudioContext;

  if (!AudioContextClass) {
    return null;
  }

  if (!alertAudioContext) {
    alertAudioContext = new AudioContextClass();
  }

  return alertAudioContext;
}

export function unlockAlertSound() {
  if (typeof window === 'undefined' || unlockBound) {
    return;
  }

  unlockBound = true;

  const unlock = () => {
    const context = getAudioContext();
    if (context?.state === 'suspended') {
      context.resume().catch(() => {});
    }

    document.removeEventListener('click', unlock);
    document.removeEventListener('keydown', unlock);
    document.removeEventListener('touchstart', unlock);
  };

  document.addEventListener('click', unlock, { passive: true });
  document.addEventListener('keydown', unlock, { passive: true });
  document.addEventListener('touchstart', unlock, { passive: true });
}

export function playAlertSound({ enabled = true, severity = 'warning' } = {}) {
  if (!enabled || typeof window === 'undefined') {
    return;
  }

  const context = getAudioContext();

  if (!context) {
    return;
  }

  if (context.state === 'suspended') {
    context.resume().then(() => playAlertSound({ enabled, severity })).catch(() => {});
    return;
  }

  const now = context.currentTime;
  const isDanger = String(severity).toLowerCase() === 'danger';
  const pattern = isDanger
    ? [{ frequency: 880, offset: 0, duration: 0.12 }, { frequency: 740, offset: 0.16, duration: 0.18 }]
    : [{ frequency: 740, offset: 0, duration: 0.12 }];

  for (const tone of pattern) {
    const oscillator = context.createOscillator();
    const gain = context.createGain();

    oscillator.type = 'sine';
    oscillator.frequency.setValueAtTime(tone.frequency, now + tone.offset);
    gain.gain.setValueAtTime(0.0001, now + tone.offset);
    gain.gain.exponentialRampToValueAtTime(0.14, now + tone.offset + 0.01);
    gain.gain.exponentialRampToValueAtTime(0.0001, now + tone.offset + tone.duration);

    oscillator.connect(gain);
    gain.connect(context.destination);
    oscillator.start(now + tone.offset);
    oscillator.stop(now + tone.offset + tone.duration + 0.02);
  }
}
