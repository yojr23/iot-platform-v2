<template>
    <div class="lab-app">
        <a class="lab-skip" href="#lab-content">Ir al contenido</a>
        <aside class="lab-sidebar" :class="{ 'is-open': open }">
            <RouterLink to="/dashboard" class="lab-brand"
                ><I name="pulse" />SINOA</RouterLink
            >
            <div class="lab-nav-caption">WORKSPACE</div>
            <nav aria-label="Principal">
                <RouterLink
                    v-for="item in links"
                    :key="item.to"
                    :to="item.to"
                    :aria-label="item.label"
                    @click="open = false"
                    ><I :name="item.icon" /><span>{{ item.label }}</span
                    ><b
                        v-if="item.to === '/alerts' && alerts.unresolvedCount"
                        >{{ alerts.unresolvedCount }}</b
                    ></RouterLink
                >
            </nav>
            <div class="lab-sidebar-foot">
                <span class="lab-dot" /> Lab Blue Workspace<small
                    >Sistema de monitoreo IoT</small
                >
            </div>
        </aside>
        <div v-if="open" class="lab-backdrop" @click="open = false" />
        <header class="lab-header">
            <button
                class="lab-icon-button lab-menu"
                aria-label="Abrir navegación"
                @click="open = !open"
            >
                <I name="menu" /></button
            ><span class="lab-mobile-brand"><I name="pulse" />SINOA</span
            ><span class="lab-header-label"
                >Sistema de Notificaciones y Alertas</span
            >
            <div class="lab-header-right">
                <span class="lab-mode">{{
                    auth.isAuthenticated
                        ? "Espacio privado"
                        : "Monitoreo público"
                }}</span
                ><button
                    v-if="auth.isAuthenticated"
                    class="lab-account"
                    @click="logout"
                >
                    <span class="lab-avatar">{{
                        auth.user?.name?.slice(0, 1) || "U"
                    }}</span
                    ><span>{{ auth.user?.name || "Mi cuenta" }}</span
                    ><I name="logout" /></button
                ><RouterLink v-else class="lab-button" to="/login"
                    >Iniciar sesión <I name="arrow"
                /></RouterLink>
            </div>
        </header>
        <main id="lab-content" class="lab-content"><slot /></main>
        <nav class="lab-bottom" aria-label="Navegación móvil">
            <RouterLink to="/dashboard"
                ><I name="home" /><span>Inicio</span></RouterLink
            ><a v-if="route.name === 'dashboard'" href="#my-charts"><I name="chart" /><span>Gráficas</span></a
            ><RouterLink v-else to="/dashboard"><I name="chart" /><span>Gráficas</span></RouterLink
            ><RouterLink v-if="auth.isAuthenticated" to="/alerts"
                ><I name="bell" /><span>Alertas</span></RouterLink
            ><RouterLink :to="auth.isAuthenticated ? '/profile' : '/login'"
                ><I :name="auth.isAuthenticated ? 'user' : 'lock'" /><span>{{
                    auth.isAuthenticated ? "Cuenta" : "Acceder"
                }}</span></RouterLink
            >
        </nav>
        <div v-if="demo" class="lab-demo-bar">
            Vista de revisión · datos simulados
            <button @click="switchDemo">
                {{ auth.isAuthenticated ? "Ver público" : "Ver privado" }}
            </button>
        </div>
    </div>
</template>
<script setup>
import { computed, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import { useAlertsStore } from "@/stores/alerts";
import I from "./LabIcon.vue";
const auth = useAuthStore(),
    alerts = useAlertsStore(),
    router = useRouter(),
    route = useRoute(),
    open = ref(false);
const demo = import.meta.env.MODE === "demo";
const links = computed(() => [
    { to: "/dashboard", label: "Dashboard", icon: "home" },
    ...(auth.isAuthenticated
        ? [
              { to: "/devices", label: "Dispositivos", icon: "device" },
              { to: "/sensors", label: "Sensores", icon: "sensor" },
              { to: "/alerts", label: "Alertas", icon: "bell" },
          ]
        : []),
    ...(auth.user?.is_admin
        ? [
              { to: "/metrics", label: "Métricas", icon: "chart" },
              { to: "/config", label: "Configuración", icon: "grip" },
          ]
        : []),
    ...(auth.isAuthenticated ? [{ to: "/profile", label: "Mi cuenta", icon: "user" }] : []),
]);
async function logout() {
    await auth.logout();
    await router.push("/dashboard");
}
async function switchDemo() {
    if (auth.isAuthenticated) await logout();
    else
        await auth.login({
            email: "demo@sinoa.local",
            password: "local-preview",
        });
}
</script>
