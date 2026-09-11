<template>
    <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <path v-for="(path, i) in paths" :key="i" :d="path" />
    </svg>
</template>
<script setup>
import { computed } from "vue";
const props = defineProps({ name: String });
const icons = {
    pulse: ["M2 12h3l2-5 3 11 3-15 3 18 2-9h4"],
    home: ["m3 10 9-7 9 7", "M5 9v12h5v-7h4v7h5V9"],
    sensor: [
        "M12 8v8",
        "M8 5a10 10 0 0 0 0 14",
        "M16 5a10 10 0 0 1 0 14",
        "M4 2a15 15 0 0 0 0 20",
        "M20 2a15 15 0 0 1 0 20",
    ],
    device: ["M6 3h12v18H6z", "M10 17h4"],
    bell: ["M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9", "M10 21h4"],
    plus: ["M12 5v14", "M5 12h14"],
    down: ["m6 9 6 6 6-6"],
    right: ["m9 5 7 7-7 7"],
    up: ["m6 15 6-6 6 6"],
    arrow: ["M5 12h14", "m14 7 5 5-5 5"],
    check: ["m5 12 4 4L19 6"],
    lock: ["M5 10h14v11H5z", "M8 10V6a4 4 0 0 1 8 0v4"],
    user: ["M16 6a4 4 0 1 1-8 0 4 4 0 0 1 8 0", "M4 22v-3a8 8 0 0 1 16 0v3"],
    menu: ["M4 6h16M4 12h16M4 18h16"],
    close: ["m6 6 12 12M6 18 18 6"],
    temp: ["M9 14.5V5a3 3 0 0 1 6 0v9.5a5 5 0 1 1-6 0", "M12 8v9"],
    drop: ["M12 2s-7 8-7 13a7 7 0 0 0 14 0c0-5-7-13-7-13Z"],
    pressure: ["M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0", "m12 12 4-5"],
    chart: ["M3 3v18h18", "m6 15 4-5 4 3 6-7"],
    search: ["m21 21-4.35-4.35", "M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"],
    refresh: ["M20 11a8 8 0 0 0-14.9-4M4 4v3h3", "M4 13a8 8 0 0 0 14.9 4M20 20v-3h-3"],
    eye: ["M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z", "M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"],
    edit: ["M12 20h9", "M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"],
    download: ["M12 3v12", "m7 10 5 5 5-5", "M5 21h14"],
    filter: ["M4 5h16", "M7 12h10", "M10 19h4"],
    alert: ["m12 3 10 18H2L12 3Z", "M12 9v5M12 17v.5"],
    grip: ["M8 5h.01M16 5h.01M8 12h.01M16 12h.01M8 19h.01M16 19h.01"],
    trash: ["M3 6h18M9 6V3h6v3M6 6l1 15h10l1-15M10 10v7M14 10v7"],
    save: ["M5 3h12l4 4v14H3V3h2Z", "M7 3v6h10V3M7 21v-8h10v8"],
    logout: ["M9 3H3v18h6", "M9 12h12m-4-4 4 4-4 4"],
    left: ["M19 12H5", "m12 19-7-7 7-7"],
    settings: ["M4 6h16", "M4 12h16", "M4 18h16", "M8 4v4M16 10v4M11 16v4"],
    mail: ["M3 5h18v14H3z", "m3 7 9 6 9-6"],
    server: ["M4 4h16v6H4z", "M4 14h16v6H4z", "M7 7h.01M7 17h.01"],
    database: ["M4 6c0-2 16-2 16 0v12c0 2-16 2-16 0V6", "M4 6c0 2 16 2 16 0M4 12c0 2 16 2 16 0"],
    shield: ["M12 3 20 6v5c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-3", "m9 12 2 2 4-4"],
};
const paths = computed(() => icons[props.name] || icons.chart);
</script>
