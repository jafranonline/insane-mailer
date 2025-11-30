import { createSignal } from 'solid-js';

const [settings, setSettings] = createSignal(window.insaneMailerAdmin?.settings || {});

export const useSettings = () => settings;

export const updateSettings = (newSettings) => {
  setSettings(newSettings);
  if (window.insaneMailerAdmin) {
    window.insaneMailerAdmin.settings = newSettings;
  }
};

export const updateSetting = (key, value) => {
  const current = settings();
  const updated = { ...current, [key]: value };
  updateSettings(updated);
};
