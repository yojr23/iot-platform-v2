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
                            name: "Vicente",
                            email: "demo@sinoa.local",
                            is_admin: false,
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
                            name: "Vicente",
                            email: "demo@sinoa.local",
                            is_admin: false,
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
