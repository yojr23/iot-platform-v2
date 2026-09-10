// Development-only fixture service. Never imported in a normal build or production server.
// Uses real frontend HTTP contracts and a minimal Pusher-compatible WebSocket protocol.
import { WebSocketServer } from "ws";
import { randomUUID } from "node:crypto";
import { readFileSync, writeFileSync, mkdirSync } from "node:fs";
export function labDemoPlugin() {
    return {
        name: "sinoa-local-demo",
        configureServer(server) {
            const devices = [
                {
                    id: 1,
                    name: "Reactor 1",
                    sensors: [
                        { id: 1, name: "Temperatura", unit: "°C" },
                        { id: 2, name: "Presión", unit: "bar" },
                    ],
                },
                {
                    id: 2,
                    name: "Cámara A",
                    sensors: [{ id: 3, name: "Humedad", unit: "%" }],
                },
                {
                    id: 3,
                    name: "Tanque B",
                    sensors: [{ id: 4, name: "Nivel", unit: "%" }],
                },
            ];
            const all = devices.flatMap((d) =>
                d.sensors.map((s) => ({ ...s, device: d })),
            );
            const tokens = new Set(),
                socketAuth = new Map();
            const epoch = Date.now();
            let layout = null;
            mkdirSync(".demo-data", { recursive: true });
            try {
                layout = JSON.parse(
                    readFileSync(".demo-data/workspace.json", "utf8"),
                );
            } catch {}
            const value = (id, t) => {
                const x = t / 10000;
                return Number(
                    (
                        [0, 27.4, 1.03, 63.2, 68.1][id] +
                        Math.sin(x * 0.18 + id) * [0, 0.8, 0.04, 1.4, 1.8][id] +
                        Math.sin(x * 0.89) * [0, 0.14, 0.007, 0.22, 0.3][id]
                    ).toFixed(2),
                );
            };
            const alerts = [
                {
                    id: 1,
                    resolved: false,
                    alert_rule: {
                        message: "Température élevée",
                        severity: "danger",
                    },
                    device: { id: 7, name: "Nodo 7" },
                    sensor: { name: "Temperatura" },
                    created_at: new Date(epoch - 180000).toISOString(),
                },
                {
                    id: 2,
                    resolved: false,
                    alert_rule: {
                        message: "Presión fuera de rango",
                        severity: "warning",
                    },
                    device: { id: 3, name: "Tanque B" },
                    sensor: { name: "Presión" },
                    created_at: new Date(epoch - 720000).toISOString(),
                },
            ];
            alerts[0].alert_rule.message = "Temperatura alta";
            const labs = [
                { id: 1, name: "Reactor Biologico A", area: "Tratamiento Secundario", process_line: "Oxigenacion", description: "Reactor aerobio principal" },
                { id: 2, name: "Reactor Biologico B", area: "Tratamiento Secundario", process_line: "Nitrificacion", description: "Reactor de soporte" },
                { id: 3, name: "Tanque de Ajuste pH", area: "Pretratamiento", process_line: "Neutralizacion", description: "Ajuste de pH de entrada" },
                { id: 4, name: "Clarificador", area: "Sedimentacion", process_line: "Clarificacion", description: "Separacion de solidos" },
                { id: 5, name: "Laboratorio de Control", area: "Analitica", process_line: "Monitoreo", description: "Validacion de calidad" },
            ];
            const sensorTypes = [
                { id: 1, name: "Temperatura", unit: "\u00b0C", min_value: 0, max_value: 100 },
                { id: 2, name: "Presi\u00f3n", unit: "bar", min_value: 0, max_value: 10 },
                { id: 3, name: "Humedad", unit: "%", min_value: 0, max_value: 100 },
                { id: 4, name: "Nivel", unit: "%", min_value: 0, max_value: 100 },
                { id: 5, name: "pH", unit: "pH", min_value: 0, max_value: 14 },
                { id: 6, name: "Oxígeno Disuelto", unit: "mg/L", min_value: 0, max_value: 20 },
                { id: 7, name: "Conductividad", unit: "μS/cm", min_value: 0, max_value: 5000 },
                { id: 8, name: "Turbidez", unit: "NTU", min_value: 0, max_value: 500 },
                { id: 9, name: "ORP", unit: "mV", min_value: -500, max_value: 500 },
                { id: 10, name: "Caudal", unit: "L/min", min_value: 0, max_value: 500 },
            ];
            const deviceTypes = [
                { id: 1, name: "Estación de Monitoreo", description: "Nodo multiparamétrico" },
                { id: 2, name: "Controlador", description: "Controlador de proceso" },
                { id: 3, name: "Analizador", description: "Analizador en línea" },
                { id: 4, name: "Bomba", description: "Bomba de recirculación" },
            ];
            const demoUsers = [
                { id: 1, name: "Admin Demo", email: "demo@sinoa.local", is_admin: true },
                { id: 2, name: "Operador", email: "operador@sinoa.local", is_admin: false },
                { id: 3, name: "Visor", email: "visor@sinoa.local", is_admin: false },
            ];
            const alertRules = [
                { id: 1, name: "Temp alta Reactor A", sensor_type_id: 1, device_id: 1, condition: ">", threshold: 28, severity: "danger", enabled: true, message: "Temperatura fuera de rango alto" },
                { id: 2, name: "pH bajo Tanque", sensor_type_id: 5, device_id: 2, condition: "<", threshold: 6.5, severity: "warning", enabled: true, message: "pH por debajo del umbral" },
                { id: 3, name: "Turbidez alta", sensor_type_id: 8, device_id: 6, condition: ">", threshold: 100, severity: "danger", enabled: true, message: "Turbidez excesiva en clarificador" },
                { id: 4, name: "OD bajo Reactor A", sensor_type_id: 6, device_id: 1, condition: "<", threshold: 2, severity: "danger", enabled: false, message: "Oxígeno disuelto crítico" },
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
                    return send(res, 200, { data: { ...device, status: "online", lab: labs[0], device_type: deviceTypes[0] } });
                }
                if (p === "/devices" && req.method === "POST") {
                    return send(res, 200, { data: { id: ++nextId, ...body, status: "online" } });
                }
                if (p === "/devices/status-snapshot") {
                    return send(res, 200, { data: devices.map((d) => ({ device_id: d.id, status: "online", is_active: true, changed_at: new Date().toISOString() })) });
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
                    return send(res, 200, { data: { total_sensors: all.length, total_devices: devices.length, active_alerts: alerts.filter((a) => !a.resolved).length, total_labs: labs.length, readings_today: 1247, uptime_percent: 99.7 } });
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
