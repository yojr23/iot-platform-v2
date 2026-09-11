// Development-only fixture service. Never imported in a normal build or production server.
// Uses real frontend HTTP contracts and a minimal Pusher-compatible WebSocket protocol.
import { WebSocketServer } from "ws";
import { randomUUID } from "node:crypto";
import { readFileSync, writeFileSync, mkdirSync } from "node:fs";
export function labDemoPlugin() {
    return {
        name: "sinoa-local-demo",
        configureServer(server) {
            const epoch = Date.now();
            const devices = [
                {
                    id: 1,
                    name: "Reactor 1",
                    location: "Planta Baja - Zona A",
                    serial_number: "SR-REACT-001",
                    firmware_version: "3.2.1",
                    ip_address: "192.168.1.101",
                    lab_id: 1,
                    device_type_id: 1,
                    status: "online",
                    last_seen: new Date(epoch - 30000).toISOString(),
                    install_date: "2024-06-15",
                    description: "Reactor biológico principal de oxigenación",
                    sensors: [
                        { id: 1, name: "Temperatura", unit: "°C", description: "Temperatura del reactor", sensor_type_id: 1 },
                        { id: 2, name: "Presión", unit: "bar", description: "Presión interna del reactor", sensor_type_id: 2 },
                        { id: 11, name: "OD Reactor 1", unit: "mg/L", description: "Oxígeno disuelto en reactor", sensor_type_id: 6 },
                    ],
                },
                {
                    id: 2,
                    name: "Cámara A",
                    location: "Planta Baja - Zona B",
                    serial_number: "SR-CAM-002",
                    firmware_version: "3.1.0",
                    ip_address: "192.168.1.102",
                    lab_id: 1,
                    device_type_id: 2,
                    status: "online",
                    last_seen: new Date(epoch - 45000).toISOString(),
                    install_date: "2024-07-20",
                    description: "Cámara de control ambiental",
                    sensors: [
                        { id: 3, name: "Humedad", unit: "%", description: "Humedad relativa ambiente", sensor_type_id: 3 },
                        { id: 12, name: "pH Cámara A", unit: "pH", description: "pH de la solución de lavado", sensor_type_id: 5 },
                    ],
                },
                {
                    id: 3,
                    name: "Tanque B",
                    location: "Planta Alta - Zona C",
                    serial_number: "SR-TANK-003",
                    firmware_version: "3.2.1",
                    ip_address: "192.168.1.103",
                    lab_id: 2,
                    device_type_id: 2,
                    status: "online",
                    last_seen: new Date(epoch - 120000).toISOString(),
                    install_date: "2024-03-10",
                    description: "Tanque de buffer de proceso",
                    sensors: [
                        { id: 4, name: "Nivel", unit: "%", description: "Nivel de llenado del tanque", sensor_type_id: 4 },
                        { id: 13, name: "Conductividad B", unit: "μS/cm", description: "Conductividad del buffer", sensor_type_id: 7 },
                    ],
                },
                {
                    id: 4,
                    name: "Estación Meta 4",
                    location: "Exterior - Zona D",
                    serial_number: "SR-META-004",
                    firmware_version: "3.0.5",
                    ip_address: "192.168.1.104",
                    lab_id: 3,
                    device_type_id: 1,
                    status: "online",
                    last_seen: new Date(epoch - 60000).toISOString(),
                    install_date: "2025-01-05",
                    description: "Estación de monitoreo exterior multiparamétrica",
                    sensors: [
                        { id: 5, name: "pH Exterior", unit: "pH", description: "pH del efluente", sensor_type_id: 5 },
                        { id: 6, name: "OD Exterior", unit: "mg/L", description: "Oxígeno disuelto efluente", sensor_type_id: 6 },
                        { id: 14, name: "Turbidez Ext", unit: "NTU", description: "Turbidez del efluente", sensor_type_id: 8 },
                    ],
                },
                {
                    id: 5,
                    name: "Bomba Principal",
                    location: "Planta Baja - Zona E",
                    serial_number: "SR-PUMP-005",
                    firmware_version: "3.2.1",
                    ip_address: "192.168.1.105",
                    lab_id: 4,
                    device_type_id: 4,
                    status: "online",
                    last_seen: new Date(epoch - 15000).toISOString(),
                    install_date: "2024-09-01",
                    description: "Bomba de recirculación principal",
                    sensors: [
                        { id: 7, name: "Caudal Bomba", unit: "L/min", description: "Caudal de recirculación", sensor_type_id: 10 },
                        { id: 15, name: "Presión Bomba", unit: "bar", description: "Presión de salida de la bomba", sensor_type_id: 2 },
                    ],
                },
                {
                    id: 6,
                    name: "Clarificador",
                    location: "Planta Baja - Zona F",
                    serial_number: "SR-CLAR-006",
                    firmware_version: "3.1.8",
                    ip_address: "192.168.1.106",
                    lab_id: 4,
                    device_type_id: 2,
                    status: "offline",
                    last_seen: new Date(epoch - 3600000).toISOString(),
                    install_date: "2024-04-22",
                    description: "Clarificador de sedimentación secundaria",
                    sensors: [
                        { id: 8, name: "Turbidez Clarif.", unit: "NTU", description: "Turbidez del clarificador", sensor_type_id: 8 },
                        { id: 16, name: "Nivel Clarif.", unit: "%", description: "Nivel de lodos del clarificador", sensor_type_id: 4 },
                    ],
                },
                {
                    id: 7,
                    name: "Nodo 7",
                    location: "Planta Alta - Zona G",
                    serial_number: "SR-NODE-007",
                    firmware_version: "3.2.0",
                    ip_address: "192.168.1.107",
                    lab_id: 5,
                    device_type_id: 1,
                    status: "online",
                    last_seen: new Date(epoch - 20000).toISOString(),
                    install_date: "2025-02-14",
                    description: "Nodo de análisis en laboratorio",
                    sensors: [
                        { id: 9, name: "ORP Laboratorio", unit: "mV", description: "Potencial de oxidación-reducción", sensor_type_id: 9 },
                        { id: 17, name: "Conductividad Lab", unit: "μS/cm", description: "Conductividad de muestra", sensor_type_id: 7 },
                    ],
                },
                {
                    id: 8,
                    name: "Estación Efluente",
                    location: "Exterior - Zona H",
                    serial_number: "SR-EFF-008",
                    firmware_version: "3.2.1",
                    ip_address: "192.168.1.108",
                    lab_id: 3,
                    device_type_id: 1,
                    status: "online",
                    last_seen: new Date(epoch - 10000).toISOString(),
                    install_date: "2025-03-01",
                    description: "Estación de monitoreo de efluente final",
                    sensors: [
                        { id: 10, name: "pH Efluente", unit: "pH", description: "pH del efluente tratado", sensor_type_id: 5 },
                        { id: 18, name: "Caudal Efluente", unit: "L/min", description: "Caudal de descarga", sensor_type_id: 10 },
                        { id: 19, name: "OD Efluente", unit: "mg/L", description: "Oxígeno disuelto efluente final", sensor_type_id: 6 },
                    ],
                },
            ];
            // Synthetic graph-safe configuration for the labelled demo only.
            // The production bootstrap obtains these intervals from RuleToGraphZones.
            const demoLimits = {
                1: [20, 28, 30],
                2: [0.8, 1.2, 1.4],
                3: [40, 70, 80],
                4: [20, 80, 90],
                5: [6, 8.5, 9],
                6: [2, 4, 5],
                7: [50, 150, 250],
                8: [15, 50, 100],
                9: [100, 250, 400],
                10: [100, 300, 450],
                11: [4, 5.5, 7],
                12: [6.5, 7.5, 8.5],
                13: [500, 1500, 3000],
                14: [20, 60, 120],
                15: [0.6, 1.0, 1.3],
                16: [30, 60, 85],
                17: [200, 800, 2500],
                18: [80, 200, 400],
                19: [3, 5, 6.5],
            };
            for (const device of devices) {
                for (const sensor of device.sensors) {
                    const [min, warning, danger] = demoLimits[sensor.id];
                    sensor.bands = [
                        { from: null, to: min, severity: "warning" },
                        { from: min, to: warning, severity: "normal" },
                        { from: warning, to: danger, severity: "warning" },
                        { from: danger, to: null, severity: "danger" },
                    ];
                }
            }
            const all = devices.flatMap((d) =>
                d.sensors.map((s) => ({ ...s, device: d })),
            );
            const tokens = new Set(),
                socketAuth = new Map();
            let layout = null;
            mkdirSync(".demo-data", { recursive: true });
            try {
                layout = JSON.parse(
                    readFileSync(".demo-data/workspace.json", "utf8"),
                );
            } catch {}
            const value = (id, t) => {
                const x = t / 10000;
                // Each sensor has a base + two harmonic components for realistic oscillation
                const bases =   [0, 27.4, 1.03, 63.2, 68.1, 7.2, 3.8, 180, 250, 5.2, 350, 5.5, 7.0, 1200, 45, 0.95, 70, 1800, 150, 4.1, 280];
                const amp1 =    [0, 0.8,  0.04, 1.4,  1.8,  0.3, 0.5, 30,  40,  0.4, 60,  0.2, 0.15, 400, 15,  0.05, 20,  300,  30,  0.3, 50];
                const freq1 =   [0, 0.18, 0.18, 0.18, 0.18, 0.15, 0.22, 0.12, 0.10, 0.20, 0.08, 0.14, 0.16, 0.06, 0.11, 0.19, 0.13, 0.07, 0.14, 0.17, 0.09];
                const amp2 =    [0, 0.14, 0.007, 0.22, 0.3, 0.06, 0.08, 5, 7, 0.07, 10, 0.04, 0.03, 80, 3, 0.01, 4, 60, 5, 0.06, 10];
                const freq2 =   [0, 0.89, 0.89, 0.89, 0.89, 0.75, 0.95, 0.60, 0.50, 0.80, 0.40, 0.85, 0.90, 0.30, 0.55, 0.88, 0.70, 0.35, 0.65, 0.82, 0.45];
                const i = Math.min(id, bases.length - 1);
                return Number(
                    (bases[i] + Math.sin(x * freq1[i] + id) * amp1[i] + Math.sin(x * freq2[i]) * amp2[i]).toFixed(2),
                );
            };
            const alerts = [
                {
                    id: 1,
                    resolved: false,
                    alert_rule: {
                        message: "Temperatura alta",
                        severity: "danger",
                    },
                    device: { id: 1, name: "Reactor 1" },
                    sensor: { id: 1, name: "Temperatura", unit: "°C" },
                    created_at: new Date(epoch - 180000).toISOString(),
                    acknowledged_by: null,
                    reading_value: 31.2,
                },
                {
                    id: 2,
                    resolved: false,
                    alert_rule: {
                        message: "Presión fuera de rango",
                        severity: "warning",
                    },
                    device: { id: 3, name: "Tanque B" },
                    sensor: { id: 2, name: "Presión", unit: "bar" },
                    created_at: new Date(epoch - 720000).toISOString(),
                    acknowledged_by: null,
                    reading_value: 1.45,
                },
                {
                    id: 3,
                    resolved: false,
                    alert_rule: {
                        message: "pH bajo en efluente",
                        severity: "danger",
                    },
                    device: { id: 8, name: "Estación Efluente" },
                    sensor: { id: 10, name: "pH Efluente", unit: "pH" },
                    created_at: new Date(epoch - 1200000).toISOString(),
                    acknowledged_by: "Operador",
                    reading_value: 5.8,
                },
                {
                    id: 4,
                    resolved: true,
                    resolved_at: new Date(epoch - 3600000).toISOString(),
                    alert_rule: {
                        message: "Turbidez elevada",
                        severity: "warning",
                    },
                    device: { id: 6, name: "Clarificador" },
                    sensor: { id: 8, name: "Turbidez Clarif.", unit: "NTU" },
                    created_at: new Date(epoch - 5400000).toISOString(),
                    acknowledged_by: "Admin Demo",
                    reading_value: 55.3,
                },
                {
                    id: 5,
                    resolved: false,
                    alert_rule: {
                        message: "Oxígeno disuelto bajo",
                        severity: "danger",
                    },
                    device: { id: 4, name: "Estación Meta 4" },
                    sensor: { id: 6, name: "OD Exterior", unit: "mg/L" },
                    created_at: new Date(epoch - 300000).toISOString(),
                    acknowledged_by: null,
                    reading_value: 1.8,
                },
                {
                    id: 6,
                    resolved: true,
                    resolved_at: new Date(epoch - 7200000).toISOString(),
                    alert_rule: {
                        message: "Caudal bajo en bomba",
                        severity: "warning",
                    },
                    device: { id: 5, name: "Bomba Principal" },
                    sensor: { id: 7, name: "Caudal Bomba", unit: "L/min" },
                    created_at: new Date(epoch - 9000000).toISOString(),
                    acknowledged_by: "Operador",
                    reading_value: 45.2,
                },
            ];
            const labs = [
                { id: 1, name: "Reactor Biologico A", area: "Tratamiento Secundario", process_line: "Oxigenacion", description: "Reactor aerobio principal", capacity_m3: 500, operator: "Ing. Carlos Mendoza", status: "active" },
                { id: 2, name: "Reactor Biologico B", area: "Tratamiento Secundario", process_line: "Nitrificacion", description: "Reactor de soporte", capacity_m3: 350, operator: "Ing. Laura Vega", status: "active" },
                { id: 3, name: "Tanque de Ajuste pH", area: "Pretratamiento", process_line: "Neutralizacion", description: "Ajuste de pH de entrada", capacity_m3: 200, operator: "Téc. Roberto Silva", status: "active" },
                { id: 4, name: "Clarificador", area: "Sedimentacion", process_line: "Clarificacion", description: "Separacion de solidos", capacity_m3: 800, operator: "Ing. Ana Torres", status: "maintenance" },
                { id: 5, name: "Laboratorio de Control", area: "Analitica", process_line: "Monitoreo", description: "Validacion de calidad", capacity_m3: null, operator: "Biol. María Rojas", status: "active" },
                { id: 6, name: "Estación de Bombeo", area: "Hidráulica", process_line: "Recirculación", description: "Bombeo de retorno de lodos", capacity_m3: 100, operator: "Téc. Juan Pérez", status: "active" },
                { id: 7, name: "Desinfección UV", area: "Tratamiento Terciario", process_line: "Desinfección", description: "Eliminación microbiana", capacity_m3: 150, operator: "Ing. Diego López", status: "active" },
            ];
            const sensorTypes = [
                { id: 1, name: "Temperatura", unit: "°C", min_value: 0, max_value: 100, description: "Temperatura ambiente o de proceso", icon: "thermometer" },
                { id: 2, name: "Presión", unit: "bar", min_value: 0, max_value: 10, description: "Presión manométrica", icon: "gauge" },
                { id: 3, name: "Humedad", unit: "%", min_value: 0, max_value: 100, description: "Humedad relativa del aire", icon: "droplet" },
                { id: 4, name: "Nivel", unit: "%", min_value: 0, max_value: 100, description: "Nivel de llenado de tanque", icon: "level" },
                { id: 5, name: "pH", unit: "pH", min_value: 0, max_value: 14, description: "Potencial de hidrógeno", icon: "ph" },
                { id: 6, name: "Oxígeno Disuelto", unit: "mg/L", min_value: 0, max_value: 20, description: "Concentración de oxígeno disuelto", icon: "oxygen" },
                { id: 7, name: "Conductividad", unit: "μS/cm", min_value: 0, max_value: 5000, description: "Capacidad de conducción eléctrica", icon: "conductivity" },
                { id: 8, name: "Turbidez", unit: "NTU", min_value: 0, max_value: 500, description: "Nivel de turbidez del agua", icon: "turbidity" },
                { id: 9, name: "ORP", unit: "mV", min_value: -500, max_value: 500, description: "Potencial de oxidación-reducción", icon: "orp" },
                { id: 10, name: "Caudal", unit: "L/min", min_value: 0, max_value: 500, description: "Flujo volumétrico", icon: "flow" },
                { id: 11, name: "Sólidos Disueltos", unit: "ppm", min_value: 0, max_value: 2000, description: "Total de sólidos disueltos", icon: "solids" },
                { id: 12, name: "Nitrógeno Total", unit: "mg/L", min_value: 0, max_value: 50, description: "Nitrógeno total Kjeldahl", icon: "nitrogen" },
                { id: 13, name: "Fósforo Total", unit: "mg/L", min_value: 0, max_value: 10, description: "Fósforo total", icon: "phosphorus" },
            ];
            const deviceTypes = [
                { id: 1, name: "Estación de Monitoreo", description: "Nodo multiparamétrico", protocol: "MQTT", refresh_interval_s: 5 },
                { id: 2, name: "Controlador", description: "Controlador de proceso", protocol: "Modbus TCP", refresh_interval_s: 2 },
                { id: 3, name: "Analizador", description: "Analizador en línea", protocol: "Modbus RTU", refresh_interval_s: 10 },
                { id: 4, name: "Bomba", description: "Bomba de recirculación", protocol: "OPC-UA", refresh_interval_s: 1 },
                { id: 5, name: "Válvula", description: "Válvula de control", protocol: "HART", refresh_interval_s: 1 },
                { id: 6, name: "Sensor Puro", description: "Sensor de un solo parámetro", protocol: "4-20mA", refresh_interval_s: 3 },
            ];
            const demoUsers = [
                { id: 1, name: "Admin Demo", email: "demo@sinoa.local", is_admin: true, role: "Administrador", department: "Ingeniería", created_at: "2025-01-15T10:00:00Z" },
                { id: 2, name: "Operador", email: "operador@sinoa.local", is_admin: false, role: "Operador", department: "Operaciones", created_at: "2025-02-20T08:00:00Z" },
                { id: 3, name: "Visor", email: "visor@sinoa.local", is_admin: false, role: "Visor", department: "Calidad", created_at: "2025-03-10T14:00:00Z" },
                { id: 4, name: "Ing. Carlos Mendoza", email: "carlos@sinoa.local", is_admin: false, role: "Supervisor", department: "Ingeniería", created_at: "2025-01-20T09:00:00Z" },
                { id: 5, name: "Biol. María Rojas", email: "maria@sinoa.local", is_admin: false, role: "Técnico", department: "Laboratorio", created_at: "2025-04-05T11:00:00Z" },
            ];
            const alertRules = [
                { id: 1, name: "Temp alta Reactor A", sensor_type_id: 1, device_id: 1, condition: ">", threshold: 28, severity: "danger", enabled: true, message: "Temperatura fuera de rango alto", cooldown_minutes: 5 },
                { id: 2, name: "pH bajo Tanque", sensor_type_id: 5, device_id: 2, condition: "<", threshold: 6.5, severity: "warning", enabled: true, message: "pH por debajo del umbral", cooldown_minutes: 10 },
                { id: 3, name: "Turbidez alta Clarif.", sensor_type_id: 8, device_id: 6, condition: ">", threshold: 100, severity: "danger", enabled: true, message: "Turbidez excesiva en clarificador", cooldown_minutes: 5 },
                { id: 4, name: "OD bajo Reactor A", sensor_type_id: 6, device_id: 1, condition: "<", threshold: 2, severity: "danger", enabled: false, message: "Oxígeno disuelto crítico", cooldown_minutes: 15 },
                { id: 5, name: "Presión alta Bomba", sensor_type_id: 2, device_id: 5, condition: ">", threshold: 1.3, severity: "warning", enabled: true, message: "Presión de salida elevada", cooldown_minutes: 5 },
                { id: 6, name: "Caudal bajo Bomba", sensor_type_id: 10, device_id: 5, condition: "<", threshold: 60, severity: "danger", enabled: true, message: "Caudal insuficiente en recirculación", cooldown_minutes: 10 },
                { id: 7, name: "Nivel alto Clarif.", sensor_type_id: 4, device_id: 6, condition: ">", threshold: 85, severity: "warning", enabled: true, message: "Nivel de lodos alto en clarificador", cooldown_minutes: 15 },
                { id: 8, name: "Conductividad alta", sensor_type_id: 7, device_id: 3, condition: ">", threshold: 3000, severity: "danger", enabled: true, message: "Conductividad fuera de especificación", cooldown_minutes: 10 },
                { id: 9, name: "pH Efluente bajo", sensor_type_id: 5, device_id: 8, condition: "<", threshold: 6.0, severity: "danger", enabled: true, message: "pH de efluente fuera de norma", cooldown_minutes: 5 },
                { id: 10, name: "ORP bajo Laboratorio", sensor_type_id: 9, device_id: 7, condition: "<", threshold: 0, severity: "warning", enabled: true, message: "ORP negativo en laboratorio", cooldown_minutes: 20 },
            ];
            let nextId = 100;
            const authorized = (req) =>
                tokens.has(
                    (req.headers.authorization || "").replace("Bearer ", ""),
                );
            const send = (res, status, data) => {
                res.statusCode = status;
                res.setHeader("Content-Type", "application/json");
                res.end(JSON.stringify(data));
            };
            server.middlewares.use(async (req, res, next) => {
                if (!req.url.startsWith("/api/")) return next();
                const url = new URL(req.url, "http://localhost"),
                    p = url.pathname.slice(4);
                let body = {};
                try {
                    if (["POST", "PUT", "PATCH"].includes(req.method)) {
                        let raw = "";
                        for await (const c of req) raw += c;
                        body = (req.headers["content-type"] || "").includes(
                            "application/x-www-form-urlencoded",
                        )
                            ? Object.fromEntries(new URLSearchParams(raw))
                            : JSON.parse(raw || "{}");
                    }
                } catch {
                    return send(res, 400, { message: "Solicitud inválida" });
                }
                if (p === "/auth/login") {
                    if (
                        body.email !== "demo@sinoa.local" ||
                        body.password !== "local-preview"
                    )
                        return send(res, 422, {
                            message: "Usa la cuenta local de demostración.",
                        });
                    const token = randomUUID();
                    tokens.add(token);
                    return send(res, 200, {
                        access_token: token,
                        user: {
                            id: 1,
                            name: "Admin Demo",
                            email: "demo@sinoa.local",
                            is_admin: true,
                        },
                    });
                }
                if (p === "/public/graph/bootstrap")
                    return send(res, 200, {
                        data: { devices, default_sensor_id: 1 },
                    });
                const match = p.match(
                    /^\/public\/graph\/sensors\/(\d+)\/series$/,
                );
                if (match) {
                    const id = Number(match[1]);
                    if (!all.find((s) => s.id === id))
                        return send(res, 404, { message: "No disponible" });
                    const from = Date.parse(url.searchParams.get("from")),
                        to = Date.parse(url.searchParams.get("to"));
                    if (
                        !Number.isFinite(from) ||
                        !Number.isFinite(to) ||
                        from >= to ||
                        to - from > 86401000
                    )
                        return send(res, 422, { message: "Ventana inválida" });
                    const step = 2000,
                        points = [];
                    for (
                        let t = Math.ceil(from / step) * step;
                        t < to;
                        t += step
                    )
                        points.push({
                            reading_id: `${id}-${t}`,
                            value: value(id, t),
                            timestamp: new Date(t).toISOString(),
                        });
                    const vals = points.map((p) => p.value);
                    return send(res, 200, {
                        data: {
                            sensor_id: id,
                            points,
                            stats: {
                                min: Math.min(...vals),
                                max: Math.max(...vals),
                                mean:
                                    vals.reduce((a, b) => a + b, 0) /
                                    vals.length,
                                count: vals.length,
                            },
                            truncated: false,
                        },
                    });
                }
                if (!authorized(req))
                    return send(res, 401, {
                        message: "Inicia sesión para acceder.",
                    });
                if (p === "/auth/me")
                    return send(res, 200, {
                        data: {
                            id: 1,
                            name: "Admin Demo",
                            email: "demo@sinoa.local",
                            is_admin: true,
                        },
                    });
                if (p === "/auth/logout") {
                    tokens.delete(
                        (req.headers.authorization || "").replace(
                            "Bearer ",
                            "",
                        ),
                    );
                    socketAuth.clear();
                    return send(res, 200, {});
                }
                if (p === "/broadcasting/auth") {
                    if (
                        !/^(private-sensor\.[1-4]|private-alerts|private-device-status)$/.test(
                            body.channel_name || "",
                        )
                    )
                        return send(res, 403, {});
                    socketAuth.set(
                        `${body.socket_id}:${body.channel_name}`,
                        true,
                    );
                    return send(res, 200, { auth: "local:preview" });
                }
                if (p === "/dashboard/preferences") {
                    if (req.method === "PUT") {
                        layout = body.layout;
                        writeFileSync(
                            ".demo-data/workspace.json",
                            JSON.stringify(layout),
                        );
                    }
                    return send(res, 200, { layout });
                }
                if (p === "/config/runtime")
                    return send(res, 200, {
                        data: { alert_sound_enabled: false },
                    });
                if (p === "/alerts/active") return send(res, 200, {alerts, count: alerts.length});
            if (p.startsWith("/alerts")) {
                    const id = p.match(/^\/alerts\/(\d+)$/)?.[1];
                    return send(
                        res,
                        200,
                        id
                            ? { data: alerts.find((a) => a.id === Number(id)) }
                            : { data: alerts, count: 2 },
                    );
                }
                if (p === "/devices")
                    return send(res, 200, {
                        data: devices.map((d) => ({ ...d, status: "online" })),
                    });
                if (p === "/dashboard/metrics")
                    return send(res, 200, { data: { latest_readings: [] } });
                if (p.match(/^\/sensors\/\d+\/latest-readings$/))
                    return send(res, 200, { data: [] });
                if (p === "/sensors") return send(res, 200, { data: all });

                // --- Auth extras ---
                if (p === "/auth/register") {
                    const token = randomUUID();
                    tokens.add(token);
                    return send(res, 200, {
                        access_token: token,
                        user: { id: 99, name: body.name || "Nuevo", email: body.email, is_admin: false },
                    });
                }
                if (p === "/auth/forgot-password") return send(res, 200, { message: "Email enviado." });
                if (p === "/auth/reset-password") return send(res, 200, { message: "Contraseña restablecida." });

                // --- Sensor detail + CRUD ---
                const sensorMatch = p.match(/^\/sensors\/(\d+)(?:\/(.*))?$/);
                if (sensorMatch) {
                    const sid = Number(sensorMatch[1]);
                    const sub = sensorMatch[2] || "";
                    const sensor = all.find((s) => s.id === sid);
                    if (!sensor) return send(res, 404, { message: "Sensor no encontrado" });
                    if (req.method === "DELETE") return send(res, 200, { message: "Eliminado" });
                    if (req.method === "PUT") return send(res, 200, { data: { ...sensor, ...body } });
                    if (sub === "readings" || sub === "readings/export") {
                        const from = Date.parse(url.searchParams.get("from") || Date.now() - 86400000);
                        const to = Date.parse(url.searchParams.get("to") || Date.now());
                        const points = [];
                        for (let t = Math.ceil(from / 2000) * 2000; t < to; t += 2000)
                            points.push({ reading_id: `${sid}-${t}`, value: value(sid, t), timestamp: new Date(t).toISOString() });
                        return send(res, 200, { data: points });
                    }
                    if (sub === "latest-readings") {
                        const now = Date.now();
                        const pts = [];
                        for (let i = 5; i >= 0; i--) {
                            const t = now - i * 60000;
                            pts.push({ reading_id: `${sid}-${t}`, value: value(sid, t), reading_time: new Date(t).toISOString() });
                        }
                        return send(res, 200, { data: pts });
                    }
                    if (req.method === "POST") return send(res, 200, { data: sensor });
                    return send(res, 200, { data: sensor });
                }
                if (p === "/sensors" && req.method === "POST") {
                    return send(res, 200, { data: { id: ++nextId, ...body, status: true } });
                }

                // --- Device detail + CRUD ---
                const deviceMatch = p.match(/^\/devices\/(\d+)(?:\/(.*))?$/);
                if (deviceMatch) {
                    const did = Number(deviceMatch[1]);
                    const sub = deviceMatch[2] || "";
                    const device = devices.find((d) => d.id === did);
                    if (!device) return send(res, 404, { message: "Dispositivo no encontrado" });
                    if (req.method === "DELETE") return send(res, 200, { message: "Eliminado" });
                    if (req.method === "PUT") return send(res, 200, { data: { ...device, ...body, status: "online" } });
                    if (sub === "sensor-list") {
                        return send(res, 200, { data: device.sensors.map((s) => ({ ...s, device_id: did })) });
                    }
                    if (sub === "status" && req.method === "POST") {
                        return send(res, 200, { data: { id: did, status: body.status ? "online" : "offline", is_active: body.status } });
                    }
                    if (req.method === "POST") return send(res, 200, { data: device });
                    return send(res, 200, { data: { ...device, status: device.status, lab: labs.find((l) => l.id === device.lab_id) || labs[0], device_type: deviceTypes.find((t) => t.id === device.device_type_id) || deviceTypes[0] } });
                }
                if (p === "/devices" && req.method === "POST") {
                    return send(res, 200, { data: { id: ++nextId, ...body, status: "online" } });
                }
                if (p === "/devices/status-snapshot") {
                    return send(res, 200, { data: devices.map((d) => ({ device_id: d.id, status: d.status, is_active: d.status === "online", changed_at: d.last_seen, ip_address: d.ip_address, firmware_version: d.firmware_version })) });
                }

                // --- Alert detail + resolve ---
                const alertMatch = p.match(/^\/alerts\/(\d+)(?:\/(.*))?$/);
                if (alertMatch) {
                    const aid = Number(alertMatch[1]);
                    const sub = alertMatch[2] || "";
                    const alert = alerts.find((a) => a.id === aid);
                    if (!alert) return send(res, 404, { message: "Alerta no encontrada" });
                    if (sub === "resolve" && req.method === "PATCH") {
                        alert.resolved = true;
                        alert.resolved_at = new Date().toISOString();
                        return send(res, 200, { data: alert });
                    }
                    return send(res, 200, { data: alert });
                }
                if (p === "/alerts/unresolved") {
                    return send(res, 200, { data: alerts.filter((a) => !a.resolved), count: alerts.filter((a) => !a.resolved).length });
                }
                if (p === "/alerts/resolve-all" && req.method === "POST") {
                    alerts.forEach((a) => { a.resolved = true; a.resolved_at = new Date().toISOString(); });
                    return send(res, 200, { message: "Todas resueltas" });
                }

                // --- Alert rules ---
                if (p === "/alert-rules/create") {
                    return send(res, 200, { data: { sensor_types: sensorTypes, devices: devices.map((d) => ({ id: d.id, name: d.name })), sensors: all.map((s) => ({ id: s.id, name: s.name, device_id: s.device.id })) } });
                }
                const alertRuleMatch = p.match(/^\/alert-rules\/(\d+)$/);
                if (alertRuleMatch) {
                    const rid = Number(alertRuleMatch[1]);
                    const rule = alertRules.find((r) => r.id === rid);
                    if (!rule) return send(res, 404, { message: "Regla no encontrada" });
                    if (req.method === "DELETE") return send(res, 200, { message: "Eliminada" });
                    if (req.method === "PUT") return send(res, 200, { data: { ...rule, ...body } });
                    return send(res, 200, { data: rule });
                }
                if (p === "/alert-rules") {
                    if (req.method === "POST") return send(res, 200, { data: { id: ++nextId, ...body, enabled: true } });
                    return send(res, 200, { data: alertRules, count: alertRules.length });
                }

                // --- Config admin ---
                if (p === "/config/alerts") {
                    if (req.method === "PUT") return send(res, 200, { message: "Actualizado" });
                    return send(res, 200, { data: { alert_sound_enabled: false, alert_email_enabled: true, alert_cooldown_minutes: 5 } });
                }
                if (p === "/config/email") {
                    if (req.method === "PUT") return send(res, 200, { message: "Actualizado" });
                    return send(res, 200, { data: { mail_host: "smtp.example.com", mail_port: 587, mail_username: "", mail_encryption: "tls", mail_from_address: "alerts@sinoa.local", configured: false } });
                }
                if (p === "/config/email/test" && req.method === "POST") {
                    return send(res, 200, { message: "Email de prueba enviado." });
                }
                if (p === "/config/system-info") {
                    return send(res, 200, { data: { php_version: "8.2", laravel_version: "11.0", app_version: "2.0.0", environment: "demo", uptime: "2d 5h", db_size: "12.4 MB", redis_connected: true } });
                }
                if (p === "/config/general" && req.method === "PUT") {
                    return send(res, 200, { message: "Actualizado" });
                }

                // --- Catalogs: labs ---
                const labMatch = p.match(/^\/labs\/(\d+)$/);
                if (labMatch) {
                    const lid = Number(labMatch[1]);
                    const lab = labs.find((l) => l.id === lid);
                    if (!lab) return send(res, 404, { message: "Lab no encontrado" });
                    if (req.method === "DELETE") return send(res, 200, { message: "Eliminado" });
                    if (req.method === "PUT") return send(res, 200, { data: { ...lab, ...body } });
                    return send(res, 200, { data: lab });
                }
                if (p === "/labs") {
                    if (req.method === "POST") return send(res, 200, { data: { id: ++nextId, ...body } });
                    return send(res, 200, { data: labs, count: labs.length });
                }

                // --- Catalogs: sensor-types ---
                const stMatch = p.match(/^\/sensor-types\/(\d+)$/);
                if (stMatch) {
                    const stid = Number(stMatch[1]);
                    const st = sensorTypes.find((s) => s.id === stid);
                    if (!st) return send(res, 404, { message: "Tipo no encontrado" });
                    if (req.method === "DELETE") return send(res, 200, { message: "Eliminado" });
                    if (req.method === "PUT") return send(res, 200, { data: { ...st, ...body } });
                    return send(res, 200, { data: st });
                }
                if (p === "/sensor-types") {
                    if (req.method === "POST") return send(res, 200, { data: { id: ++nextId, ...body } });
                    return send(res, 200, { data: sensorTypes, count: sensorTypes.length });
                }

                // --- Catalogs: device-types ---
                const dtMatch = p.match(/^\/device-types\/(\d+)$/);
                if (dtMatch) {
                    const dtid = Number(dtMatch[1]);
                    const dt = deviceTypes.find((d) => d.id === dtid);
                    if (!dt) return send(res, 404, { message: "Tipo no encontrado" });
                    if (req.method === "DELETE") return send(res, 200, { message: "Eliminado" });
                    if (req.method === "PUT") return send(res, 200, { data: { ...dt, ...body } });
                    return send(res, 200, { data: dt });
                }
                if (p === "/device-types") {
                    if (req.method === "POST") return send(res, 200, { data: { id: ++nextId, ...body } });
                    return send(res, 200, { data: deviceTypes, count: deviceTypes.length });
                }

                // --- Users ---
                const userRoleMatch = p.match(/^\/users\/(\d+)\/role$/);
                if (userRoleMatch) {
                    const uid = Number(userRoleMatch[1]);
                    const user = demoUsers.find((u) => u.id === uid);
                    if (!user) return send(res, 404, { message: "Usuario no encontrado" });
                    user.is_admin = body.is_admin;
                    return send(res, 200, { data: user });
                }
                if (p === "/users") {
                    return send(res, 200, { data: demoUsers, count: demoUsers.length });
                }

                // --- Profile ---
                if (p === "/profile") {
                    return send(res, 200, { data: { id: 1, name: "Admin Demo", email: "demo@sinoa.local", is_admin: true, created_at: "2025-01-15T10:00:00Z" } });
                }

                // --- Metrics ---
                if (p === "/metrics") {
                    return send(res, 200, { data: { total_sensors: all.length, total_devices: devices.length, active_alerts: alerts.filter((a) => !a.resolved).length, total_labs: labs.length, readings_today: 4287, uptime_percent: 98.3, online_devices: devices.filter((d) => d.status === "online").length, offline_devices: devices.filter((d) => d.status === "offline").length, total_alert_rules: alertRules.length, enabled_rules: alertRules.filter((r) => r.enabled).length } });
                }

                return send(res, 404, {
                    message:
                        "Esta vista no forma parte de la demostración local.",
                });
            });
            const wss = new WebSocketServer({ noServer: true });
            const onUpgrade = (req, socket, head) => {
                if (req.url.startsWith("/app/"))
                    wss.handleUpgrade(req, socket, head, (ws) =>
                        wss.emit("connection", ws),
                    );
            };
            server.httpServer.on("upgrade", onUpgrade);
            wss.on("connection", (ws) => {
                ws.channels = new Set();
                ws.sid = `${Date.now()}.${Math.floor(Math.random() * 1e5)}`;
                ws.send(
                    JSON.stringify({
                        event: "pusher:connection_established",
                        data: JSON.stringify({
                            socket_id: ws.sid,
                            activity_timeout: 120,
                        }),
                    }),
                );
                ws.on("message", (raw) => {
                    try {
                        const m = JSON.parse(raw);
                        if (m.event === "pusher:ping")
                            ws.send(
                                JSON.stringify({
                                    event: "pusher:pong",
                                    data: "{}",
                                }),
                            );
                        if (m.event === "pusher:subscribe") {
                            const ch = m.data.channel;
                            if (
                                ch.startsWith("private-") &&
                                !socketAuth.has(`${ws.sid}:${ch}`)
                            )
                                return;
                            ws.channels.add(ch);
                            ws.send(
                                JSON.stringify({
                                    event: "pusher_internal:subscription_succeeded",
                                    channel: ch,
                                    data: "{}",
                                }),
                            );
                        }
                        if (m.event === "pusher:unsubscribe")
                            ws.channels.delete(m.data.channel);
                    } catch {}
                });
            });
            const timer = setInterval(() => {
                const t = Math.floor(Date.now() / 2000) * 2000;
                for (const ws of wss.clients) {
                    for (const ch of ws.channels) {
                        if (
                            ch.startsWith("private-") &&
                            !socketAuth.has(`${ws.sid}:${ch}`)
                        )
                            continue;
                        const id = Number(
                            ch.match(/^(?:private-)?sensor\.(\d+)$/)?.[1],
                        );
                        if (!all.find((s) => s.id === id)) continue;
                        ws.send(
                            JSON.stringify({
                                event: "App\\Events\\NewSensorReading",
                                channel: ch,
                                data: JSON.stringify({
                                    reading_id: `${id}-${t}`,
                                    sensor_id: id,
                                    value: value(id, t),
                                    reading_time: new Date(t).toISOString(),
                                }),
                            }),
                        );
                    }
                }
            }, 2000);
            server.httpServer.once("close", () => {
                clearInterval(timer);
                wss.close();
                server.httpServer.off("upgrade", onUpgrade);
            });
        },
    };
}
