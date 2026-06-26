import { defineStore } from 'pinia';

import * as authApi from '@/api/auth';
import {
  clearStoredToken,
  getApiErrorMessage,
  getStoredToken,
  setStoredToken,
  unwrapData
} from '@/api/client';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: getStoredToken(),
    loading: false,
    error: null,
    initialized: false
  }),

  getters: {
    isAuthenticated: (state) => Boolean(state.token && state.user)
  },

  actions: {
    setToken(token) {
      this.token = token;
      setStoredToken(token);
    },

    clearAuth() {
      this.user = null;
      this.token = null;
      this.error = null;
      clearStoredToken();
    },

    async initializeAuth() {
      if (this.initialized) {
        return;
      }

      this.token = getStoredToken();

      if (!this.token) {
        this.initialized = true;
        return;
      }

      try {
        await this.fetchUser();
      } catch {
        this.clearAuth();
      } finally {
        this.initialized = true;
      }
    },

    async fetchUser() {
      this.loading = true;
      this.error = null;

      try {
        const response = await authApi.me();
        this.user = unwrapData(response);
        return this.user;
      } catch (error) {
        this.error = getApiErrorMessage(error, 'No se pudo cargar la sesion.');
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async login(credentials) {
      this.loading = true;
      this.error = null;

      try {
        const response = await authApi.login(credentials);
        const payload = response.data;
        const token = payload.access_token || payload.token;

        if (!token) {
          throw new Error('La API no devolvio un token de acceso.');
        }

        this.setToken(token);
        this.user = payload.user || null;
        await this.fetchUser();

        return this.user;
      } catch (error) {
        this.clearAuth();
        this.error = getApiErrorMessage(error, 'No se pudo iniciar sesion.');
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async logout() {
      this.loading = true;

      try {
        if (this.token) {
          await authApi.logout();
        }
      } finally {
        this.clearAuth();
        this.initialized = true;
        this.loading = false;
      }
    }
  }
});
