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
    alert: ["m12 3 10 18H2L12 3Z", "M12 9v5M12 17v.5"],
    grip: ["M8 5h.01M16 5h.01M8 12h.01M16 12h.01M8 19h.01M16 19h.01"],
    trash: ["M3 6h18M9 6V3h6v3M6 6l1 15h10l1-15M10 10v7M14 10v7"],
    save: ["M5 3h12l4 4v14H3V3h2Z", "M7 3v6h10V3M7 21v-8h10v8"],
    logout: ["M9 3H3v18h6", "M9 12h12m-4-4 4 4-4 4"],
};
const paths = computed(() => icons[props.name] || icons.chart);
</script>
