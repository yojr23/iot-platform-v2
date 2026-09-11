// scripts/demo-server.mjs
import { WebSocketServer } from "file:///C:/Users/jvrincon/Documents/iot_platform/iot-platform-v2/front/node_modules/ws/wrapper.mjs";
import { randomUUID } from "node:crypto";
import { readFileSync, writeFileSync, mkdirSync } from "node:fs";
function labDemoPlugin() {
  return {
    name: "sinoa-local-demo",
    configureServer(server) {
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
          last_seen: new Date(epoch - 3e4).toISOString(),
          install_date: "2024-06-15",
          description: "Reactor biol\xF3gico principal de oxigenaci\xF3n",
          sensors: [
            { id: 1, name: "Temperatura", unit: "\xB0C", description: "Temperatura del reactor", sensor_type_id: 1 },
            { id: 2, name: "Presi\xF3n", unit: "bar", description: "Presi\xF3n interna del reactor", sensor_type_id: 2 },
            { id: 11, name: "OD Reactor 1", unit: "mg/L", description: "Ox\xEDgeno disuelto en reactor", sensor_type_id: 6 }
          ]
        },
        {
          id: 2,
          name: "C\xE1mara A",
          location: "Planta Baja - Zona B",
          serial_number: "SR-CAM-002",
          firmware_version: "3.1.0",
          ip_address: "192.168.1.102",
          lab_id: 1,
          device_type_id: 2,
          status: "online",
          last_seen: new Date(epoch - 45e3).toISOString(),
          install_date: "2024-07-20",
          description: "C\xE1mara de control ambiental",
          sensors: [
            { id: 3, name: "Humedad", unit: "%", description: "Humedad relativa ambiente", sensor_type_id: 3 },
            { id: 12, name: "pH C\xE1mara A", unit: "pH", description: "pH de la soluci\xF3n de lavado", sensor_type_id: 5 }
          ]
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
          last_seen: new Date(epoch - 12e4).toISOString(),
          install_date: "2024-03-10",
          description: "Tanque de buffer de proceso",
          sensors: [
            { id: 4, name: "Nivel", unit: "%", description: "Nivel de llenado del tanque", sensor_type_id: 4 },
            { id: 13, name: "Conductividad B", unit: "\u03BCS/cm", description: "Conductividad del buffer", sensor_type_id: 7 }
          ]
        },
        {
          id: 4,
          name: "Estaci\xF3n Meta 4",
          location: "Exterior - Zona D",
          serial_number: "SR-META-004",
          firmware_version: "3.0.5",
          ip_address: "192.168.1.104",
          lab_id: 3,
          device_type_id: 1,
          status: "online",
          last_seen: new Date(epoch - 6e4).toISOString(),
          install_date: "2025-01-05",
          description: "Estaci\xF3n de monitoreo exterior multiparam\xE9trica",
          sensors: [
            { id: 5, name: "pH Exterior", unit: "pH", description: "pH del efluente", sensor_type_id: 5 },
            { id: 6, name: "OD Exterior", unit: "mg/L", description: "Ox\xEDgeno disuelto efluente", sensor_type_id: 6 },
            { id: 14, name: "Turbidez Ext", unit: "NTU", description: "Turbidez del efluente", sensor_type_id: 8 }
          ]
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
          last_seen: new Date(epoch - 15e3).toISOString(),
          install_date: "2024-09-01",
          description: "Bomba de recirculaci\xF3n principal",
          sensors: [
            { id: 7, name: "Caudal Bomba", unit: "L/min", description: "Caudal de recirculaci\xF3n", sensor_type_id: 10 },
            { id: 15, name: "Presi\xF3n Bomba", unit: "bar", description: "Presi\xF3n de salida de la bomba", sensor_type_id: 2 }
          ]
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
          last_seen: new Date(epoch - 36e5).toISOString(),
          install_date: "2024-04-22",
          description: "Clarificador de sedimentaci\xF3n secundaria",
          sensors: [
            { id: 8, name: "Turbidez Clarif.", unit: "NTU", description: "Turbidez del clarificador", sensor_type_id: 8 },
            { id: 16, name: "Nivel Clarif.", unit: "%", description: "Nivel de lodos del clarificador", sensor_type_id: 4 }
          ]
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
          last_seen: new Date(epoch - 2e4).toISOString(),
          install_date: "2025-02-14",
          description: "Nodo de an\xE1lisis en laboratorio",
          sensors: [
            { id: 9, name: "ORP Laboratorio", unit: "mV", description: "Potencial de oxidaci\xF3n-reducci\xF3n", sensor_type_id: 9 },
            { id: 17, name: "Conductividad Lab", unit: "\u03BCS/cm", description: "Conductividad de muestra", sensor_type_id: 7 }
          ]
        },
        {
          id: 8,
          name: "Estaci\xF3n Efluente",
          location: "Exterior - Zona H",
          serial_number: "SR-EFF-008",
          firmware_version: "3.2.1",
          ip_address: "192.168.1.108",
          lab_id: 3,
          device_type_id: 1,
          status: "online",
          last_seen: new Date(epoch - 1e4).toISOString(),
          install_date: "2025-03-01",
          description: "Estaci\xF3n de monitoreo de efluente final",
          sensors: [
            { id: 10, name: "pH Efluente", unit: "pH", description: "pH del efluente tratado", sensor_type_id: 5 },
            { id: 18, name: "Caudal Efluente", unit: "L/min", description: "Caudal de descarga", sensor_type_id: 10 },
            { id: 19, name: "OD Efluente", unit: "mg/L", description: "Ox\xEDgeno disuelto efluente final", sensor_type_id: 6 }
          ]
        }
      ];
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
        13: [500, 1500, 3e3],
        14: [20, 60, 120],
        15: [0.6, 1, 1.3],
        16: [30, 60, 85],
        17: [200, 800, 2500],
        18: [80, 200, 400],
        19: [3, 5, 6.5]
      };
      for (const device of devices) {
        for (const sensor of device.sensors) {
          const [min, warning, danger] = demoLimits[sensor.id];
          sensor.bands = [
            { from: null, to: min, severity: "warning" },
            { from: min, to: warning, severity: "normal" },
            { from: warning, to: danger, severity: "warning" },
            { from: danger, to: null, severity: "danger" }
          ];
        }
      }
      const all = devices.flatMap(
        (d) => d.sensors.map((s) => ({ ...s, device: d }))
      );
      const tokens = /* @__PURE__ */ new Set(), socketAuth = /* @__PURE__ */ new Map();
      const epoch = Date.now();
      let layout = null;
      mkdirSync(".demo-data", { recursive: true });
      try {
        layout = JSON.parse(
          readFileSync(".demo-data/workspace.json", "utf8")
        );
      } catch {
      }
      const value = (id, t) => {
        const x = t / 1e4;
        const bases = [0, 27.4, 1.03, 63.2, 68.1, 7.2, 3.8, 180, 250, 5.2, 350, 5.5, 7, 1200, 45, 0.95, 70, 1800, 150, 4.1, 280];
        const amp1 = [0, 0.8, 0.04, 1.4, 1.8, 0.3, 0.5, 30, 40, 0.4, 60, 0.2, 0.15, 400, 15, 0.05, 20, 300, 30, 0.3, 50];
        const freq1 = [0, 0.18, 0.18, 0.18, 0.18, 0.15, 0.22, 0.12, 0.1, 0.2, 0.08, 0.14, 0.16, 0.06, 0.11, 0.19, 0.13, 0.07, 0.14, 0.17, 0.09];
        const amp2 = [0, 0.14, 7e-3, 0.22, 0.3, 0.06, 0.08, 5, 7, 0.07, 10, 0.04, 0.03, 80, 3, 0.01, 4, 60, 5, 0.06, 10];
        const freq2 = [0, 0.89, 0.89, 0.89, 0.89, 0.75, 0.95, 0.6, 0.5, 0.8, 0.4, 0.85, 0.9, 0.3, 0.55, 0.88, 0.7, 0.35, 0.65, 0.82, 0.45];
        const i = Math.min(id, bases.length - 1);
        return Number(
          (bases[i] + Math.sin(x * freq1[i] + id) * amp1[i] + Math.sin(x * freq2[i]) * amp2[i]).toFixed(2)
        );
      };
      const alerts = [
        {
          id: 1,
          resolved: false,
          alert_rule: {
            message: "Temperatura alta",
            severity: "danger"
          },
          device: { id: 1, name: "Reactor 1" },
          sensor: { id: 1, name: "Temperatura", unit: "\xB0C" },
          created_at: new Date(epoch - 18e4).toISOString(),
          acknowledged_by: null,
          reading_value: 31.2
        },
        {
          id: 2,
          resolved: false,
          alert_rule: {
            message: "Presi\xF3n fuera de rango",
            severity: "warning"
          },
          device: { id: 3, name: "Tanque B" },
          sensor: { id: 2, name: "Presi\xF3n", unit: "bar" },
          created_at: new Date(epoch - 72e4).toISOString(),
          acknowledged_by: null,
          reading_value: 1.45
        },
        {
          id: 3,
          resolved: false,
          alert_rule: {
            message: "pH bajo en efluente",
            severity: "danger"
          },
          device: { id: 8, name: "Estaci\xF3n Efluente" },
          sensor: { id: 10, name: "pH Efluente", unit: "pH" },
          created_at: new Date(epoch - 12e5).toISOString(),
          acknowledged_by: "Operador",
          reading_value: 5.8
        },
        {
          id: 4,
          resolved: true,
          resolved_at: new Date(epoch - 36e5).toISOString(),
          alert_rule: {
            message: "Turbidez elevada",
            severity: "warning"
          },
          device: { id: 6, name: "Clarificador" },
          sensor: { id: 8, name: "Turbidez Clarif.", unit: "NTU" },
          created_at: new Date(epoch - 54e5).toISOString(),
          acknowledged_by: "Admin Demo",
          reading_value: 55.3
        },
        {
          id: 5,
          resolved: false,
          alert_rule: {
            message: "Ox\xEDgeno disuelto bajo",
            severity: "danger"
          },
          device: { id: 4, name: "Estaci\xF3n Meta 4" },
          sensor: { id: 6, name: "OD Exterior", unit: "mg/L" },
          created_at: new Date(epoch - 3e5).toISOString(),
          acknowledged_by: null,
          reading_value: 1.8
        },
        {
          id: 6,
          resolved: true,
          resolved_at: new Date(epoch - 72e5).toISOString(),
          alert_rule: {
            message: "Caudal bajo en bomba",
            severity: "warning"
          },
          device: { id: 5, name: "Bomba Principal" },
          sensor: { id: 7, name: "Caudal Bomba", unit: "L/min" },
          created_at: new Date(epoch - 9e6).toISOString(),
          acknowledged_by: "Operador",
          reading_value: 45.2
        }
      ];
      const labs = [
        { id: 1, name: "Reactor Biologico A", area: "Tratamiento Secundario", process_line: "Oxigenacion", description: "Reactor aerobio principal", capacity_m3: 500, operator: "Ing. Carlos Mendoza", status: "active" },
        { id: 2, name: "Reactor Biologico B", area: "Tratamiento Secundario", process_line: "Nitrificacion", description: "Reactor de soporte", capacity_m3: 350, operator: "Ing. Laura Vega", status: "active" },
        { id: 3, name: "Tanque de Ajuste pH", area: "Pretratamiento", process_line: "Neutralizacion", description: "Ajuste de pH de entrada", capacity_m3: 200, operator: "T\xE9c. Roberto Silva", status: "active" },
        { id: 4, name: "Clarificador", area: "Sedimentacion", process_line: "Clarificacion", description: "Separacion de solidos", capacity_m3: 800, operator: "Ing. Ana Torres", status: "maintenance" },
        { id: 5, name: "Laboratorio de Control", area: "Analitica", process_line: "Monitoreo", description: "Validacion de calidad", capacity_m3: null, operator: "Biol. Mar\xEDa Rojas", status: "active" },
        { id: 6, name: "Estaci\xF3n de Bombeo", area: "Hidr\xE1ulica", process_line: "Recirculaci\xF3n", description: "Bombeo de retorno de lodos", capacity_m3: 100, operator: "T\xE9c. Juan P\xE9rez", status: "active" },
        { id: 7, name: "Desinfecci\xF3n UV", area: "Tratamiento Terciario", process_line: "Desinfecci\xF3n", description: "Eliminaci\xF3n microbiana", capacity_m3: 150, operator: "Ing. Diego L\xF3pez", status: "active" }
      ];
      const sensorTypes = [
        { id: 1, name: "Temperatura", unit: "\xB0C", min_value: 0, max_value: 100, description: "Temperatura ambiente o de proceso", icon: "thermometer" },
        { id: 2, name: "Presi\xF3n", unit: "bar", min_value: 0, max_value: 10, description: "Presi\xF3n manom\xE9trica", icon: "gauge" },
        { id: 3, name: "Humedad", unit: "%", min_value: 0, max_value: 100, description: "Humedad relativa del aire", icon: "droplet" },
        { id: 4, name: "Nivel", unit: "%", min_value: 0, max_value: 100, description: "Nivel de llenado de tanque", icon: "level" },
        { id: 5, name: "pH", unit: "pH", min_value: 0, max_value: 14, description: "Potencial de hidr\xF3geno", icon: "ph" },
        { id: 6, name: "Ox\xEDgeno Disuelto", unit: "mg/L", min_value: 0, max_value: 20, description: "Concentraci\xF3n de ox\xEDgeno disuelto", icon: "oxygen" },
        { id: 7, name: "Conductividad", unit: "\u03BCS/cm", min_value: 0, max_value: 5e3, description: "Capacidad de conducci\xF3n el\xE9ctrica", icon: "conductivity" },
        { id: 8, name: "Turbidez", unit: "NTU", min_value: 0, max_value: 500, description: "Nivel de turbidez del agua", icon: "turbidity" },
        { id: 9, name: "ORP", unit: "mV", min_value: -500, max_value: 500, description: "Potencial de oxidaci\xF3n-reducci\xF3n", icon: "orp" },
        { id: 10, name: "Caudal", unit: "L/min", min_value: 0, max_value: 500, description: "Flujo volum\xE9trico", icon: "flow" },
        { id: 11, name: "S\xF3lidos Disueltos", unit: "ppm", min_value: 0, max_value: 2e3, description: "Total de s\xF3lidos disueltos", icon: "solids" },
        { id: 12, name: "Nitr\xF3geno Total", unit: "mg/L", min_value: 0, max_value: 50, description: "Nitr\xF3geno total Kjeldahl", icon: "nitrogen" },
        { id: 13, name: "F\xF3sforo Total", unit: "mg/L", min_value: 0, max_value: 10, description: "F\xF3sforo total", icon: "phosphorus" }
      ];
      const deviceTypes = [
        { id: 1, name: "Estaci\xF3n de Monitoreo", description: "Nodo multiparam\xE9trico", protocol: "MQTT", refresh_interval_s: 5 },
        { id: 2, name: "Controlador", description: "Controlador de proceso", protocol: "Modbus TCP", refresh_interval_s: 2 },
        { id: 3, name: "Analizador", description: "Analizador en l\xEDnea", protocol: "Modbus RTU", refresh_interval_s: 10 },
        { id: 4, name: "Bomba", description: "Bomba de recirculaci\xF3n", protocol: "OPC-UA", refresh_interval_s: 1 },
        { id: 5, name: "V\xE1lvula", description: "V\xE1lvula de control", protocol: "HART", refresh_interval_s: 1 },
        { id: 6, name: "Sensor Puro", description: "Sensor de un solo par\xE1metro", protocol: "4-20mA", refresh_interval_s: 3 }
      ];
      const demoUsers = [
        { id: 1, name: "Admin Demo", email: "demo@sinoa.local", is_admin: true, role: "Administrador", department: "Ingenier\xEDa", created_at: "2025-01-15T10:00:00Z" },
        { id: 2, name: "Operador", email: "operador@sinoa.local", is_admin: false, role: "Operador", department: "Operaciones", created_at: "2025-02-20T08:00:00Z" },
        { id: 3, name: "Visor", email: "visor@sinoa.local", is_admin: false, role: "Visor", department: "Calidad", created_at: "2025-03-10T14:00:00Z" },
        { id: 4, name: "Ing. Carlos Mendoza", email: "carlos@sinoa.local", is_admin: false, role: "Supervisor", department: "Ingenier\xEDa", created_at: "2025-01-20T09:00:00Z" },
        { id: 5, name: "Biol. Mar\xEDa Rojas", email: "maria@sinoa.local", is_admin: false, role: "T\xE9cnico", department: "Laboratorio", created_at: "2025-04-05T11:00:00Z" }
      ];
      const alertRules = [
        { id: 1, name: "Temp alta Reactor A", sensor_type_id: 1, device_id: 1, condition: ">", threshold: 28, severity: "danger", enabled: true, message: "Temperatura fuera de rango alto", cooldown_minutes: 5 },
        { id: 2, name: "pH bajo Tanque", sensor_type_id: 5, device_id: 2, condition: "<", threshold: 6.5, severity: "warning", enabled: true, message: "pH por debajo del umbral", cooldown_minutes: 10 },
        { id: 3, name: "Turbidez alta Clarif.", sensor_type_id: 8, device_id: 6, condition: ">", threshold: 100, severity: "danger", enabled: true, message: "Turbidez excesiva en clarificador", cooldown_minutes: 5 },
        { id: 4, name: "OD bajo Reactor A", sensor_type_id: 6, device_id: 1, condition: "<", threshold: 2, severity: "danger", enabled: false, message: "Ox\xEDgeno disuelto cr\xEDtico", cooldown_minutes: 15 },
        { id: 5, name: "Presi\xF3n alta Bomba", sensor_type_id: 2, device_id: 5, condition: ">", threshold: 1.3, severity: "warning", enabled: true, message: "Presi\xF3n de salida elevada", cooldown_minutes: 5 },
        { id: 6, name: "Caudal bajo Bomba", sensor_type_id: 10, device_id: 5, condition: "<", threshold: 60, severity: "danger", enabled: true, message: "Caudal insuficiente en recirculaci\xF3n", cooldown_minutes: 10 },
        { id: 7, name: "Nivel alto Clarif.", sensor_type_id: 4, device_id: 6, condition: ">", threshold: 85, severity: "warning", enabled: true, message: "Nivel de lodos alto en clarificador", cooldown_minutes: 15 },
        { id: 8, name: "Conductividad alta", sensor_type_id: 7, device_id: 3, condition: ">", threshold: 3e3, severity: "danger", enabled: true, message: "Conductividad fuera de especificaci\xF3n", cooldown_minutes: 10 },
        { id: 9, name: "pH Efluente bajo", sensor_type_id: 5, device_id: 8, condition: "<", threshold: 6, severity: "danger", enabled: true, message: "pH de efluente fuera de norma", cooldown_minutes: 5 },
        { id: 10, name: "ORP bajo Laboratorio", sensor_type_id: 9, device_id: 7, condition: "<", threshold: 0, severity: "warning", enabled: true, message: "ORP negativo en laboratorio", cooldown_minutes: 20 }
      ];
      let nextId = 100;
      const authorized = (req) => tokens.has(
        (req.headers.authorization || "").replace("Bearer ", "")
      );
      const send = (res, status, data) => {
        res.statusCode = status;
        res.setHeader("Content-Type", "application/json");
        res.end(JSON.stringify(data));
      };
      server.middlewares.use(async (req, res, next) => {
        if (!req.url.startsWith("/api/")) return next();
        const url = new URL(req.url, "http://localhost"), p = url.pathname.slice(4);
        let body = {};
        try {
          if (["POST", "PUT", "PATCH"].includes(req.method)) {
            let raw = "";
            for await (const c of req) raw += c;
            body = (req.headers["content-type"] || "").includes(
              "application/x-www-form-urlencoded"
            ) ? Object.fromEntries(new URLSearchParams(raw)) : JSON.parse(raw || "{}");
          }
        } catch {
          return send(res, 400, { message: "Solicitud inv\xE1lida" });
        }
        if (p === "/auth/login") {
          if (body.email !== "demo@sinoa.local" || body.password !== "local-preview")
            return send(res, 422, {
              message: "Usa la cuenta local de demostraci\xF3n."
            });
          const token = randomUUID();
          tokens.add(token);
          return send(res, 200, {
            access_token: token,
            user: {
              id: 1,
              name: "Admin Demo",
              email: "demo@sinoa.local",
              is_admin: true
            }
          });
        }
        if (p === "/public/graph/bootstrap")
          return send(res, 200, {
            data: { devices, default_sensor_id: 1 }
          });
        const match = p.match(
          /^\/public\/graph\/sensors\/(\d+)\/series$/
        );
        if (match) {
          const id = Number(match[1]);
          if (!all.find((s) => s.id === id))
            return send(res, 404, { message: "No disponible" });
          const from = Date.parse(url.searchParams.get("from")), to = Date.parse(url.searchParams.get("to"));
          if (!Number.isFinite(from) || !Number.isFinite(to) || from >= to || to - from > 86401e3)
            return send(res, 422, { message: "Ventana inv\xE1lida" });
          const step = 2e3, points = [];
          for (let t = Math.ceil(from / step) * step; t < to; t += step)
            points.push({
              reading_id: `${id}-${t}`,
              value: value(id, t),
              timestamp: new Date(t).toISOString()
            });
          const vals = points.map((p2) => p2.value);
          return send(res, 200, {
            data: {
              sensor_id: id,
              points,
              stats: {
                min: Math.min(...vals),
                max: Math.max(...vals),
                mean: vals.reduce((a, b) => a + b, 0) / vals.length,
                count: vals.length
              },
              truncated: false
            }
          });
        }
        if (!authorized(req))
          return send(res, 401, {
            message: "Inicia sesi\xF3n para acceder."
          });
        if (p === "/auth/me")
          return send(res, 200, {
            data: {
              id: 1,
              name: "Admin Demo",
              email: "demo@sinoa.local",
              is_admin: true
            }
          });
        if (p === "/auth/logout") {
          tokens.delete(
            (req.headers.authorization || "").replace(
              "Bearer ",
              ""
            )
          );
          socketAuth.clear();
          return send(res, 200, {});
        }
        if (p === "/broadcasting/auth") {
          if (!/^(private-sensor\.[1-4]|private-alerts|private-device-status)$/.test(
            body.channel_name || ""
          ))
            return send(res, 403, {});
          socketAuth.set(
            `${body.socket_id}:${body.channel_name}`,
            true
          );
          return send(res, 200, { auth: "local:preview" });
        }
        if (p === "/dashboard/preferences") {
          if (req.method === "PUT") {
            layout = body.layout;
            writeFileSync(
              ".demo-data/workspace.json",
              JSON.stringify(layout)
            );
          }
          return send(res, 200, { layout });
        }
        if (p === "/config/runtime")
          return send(res, 200, {
            data: { alert_sound_enabled: false }
          });
        if (p === "/alerts/active") return send(res, 200, { alerts, count: alerts.length });
        if (p.startsWith("/alerts")) {
          const id = p.match(/^\/alerts\/(\d+)$/)?.[1];
          return send(
            res,
            200,
            id ? { data: alerts.find((a) => a.id === Number(id)) } : { data: alerts, count: 2 }
          );
        }
        if (p === "/devices")
          return send(res, 200, {
            data: devices.map((d) => ({ ...d, status: "online" }))
          });
        if (p === "/dashboard/metrics")
          return send(res, 200, { data: { latest_readings: [] } });
        if (p.match(/^\/sensors\/\d+\/latest-readings$/))
          return send(res, 200, { data: [] });
        if (p === "/sensors") return send(res, 200, { data: all });
        if (p === "/auth/register") {
          const token = randomUUID();
          tokens.add(token);
          return send(res, 200, {
            access_token: token,
            user: { id: 99, name: body.name || "Nuevo", email: body.email, is_admin: false }
          });
        }
        if (p === "/auth/forgot-password") return send(res, 200, { message: "Email enviado." });
        if (p === "/auth/reset-password") return send(res, 200, { message: "Contrase\xF1a restablecida." });
        const sensorMatch = p.match(/^\/sensors\/(\d+)(?:\/(.*))?$/);
        if (sensorMatch) {
          const sid = Number(sensorMatch[1]);
          const sub = sensorMatch[2] || "";
          const sensor = all.find((s) => s.id === sid);
          if (!sensor) return send(res, 404, { message: "Sensor no encontrado" });
          if (req.method === "DELETE") return send(res, 200, { message: "Eliminado" });
          if (req.method === "PUT") return send(res, 200, { data: { ...sensor, ...body } });
          if (sub === "readings" || sub === "readings/export") {
            const from = Date.parse(url.searchParams.get("from") || Date.now() - 864e5);
            const to = Date.parse(url.searchParams.get("to") || Date.now());
            const points = [];
            for (let t = Math.ceil(from / 2e3) * 2e3; t < to; t += 2e3)
              points.push({ reading_id: `${sid}-${t}`, value: value(sid, t), timestamp: new Date(t).toISOString() });
            return send(res, 200, { data: points });
          }
          if (sub === "latest-readings") {
            const now = Date.now();
            const pts = [];
            for (let i = 5; i >= 0; i--) {
              const t = now - i * 6e4;
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
          return send(res, 200, { data: devices.map((d) => ({ device_id: d.id, status: d.status, is_active: d.status === "online", changed_at: d.last_seen, ip_address: d.ip_address, firmware_version: d.firmware_version })) });
        }
        const alertMatch = p.match(/^\/alerts\/(\d+)(?:\/(.*))?$/);
        if (alertMatch) {
          const aid = Number(alertMatch[1]);
          const sub = alertMatch[2] || "";
          const alert = alerts.find((a) => a.id === aid);
          if (!alert) return send(res, 404, { message: "Alerta no encontrada" });
          if (sub === "resolve" && req.method === "PATCH") {
            alert.resolved = true;
            alert.resolved_at = (/* @__PURE__ */ new Date()).toISOString();
            return send(res, 200, { data: alert });
          }
          return send(res, 200, { data: alert });
        }
        if (p === "/alerts/unresolved") {
          return send(res, 200, { data: alerts.filter((a) => !a.resolved), count: alerts.filter((a) => !a.resolved).length });
        }
        if (p === "/alerts/resolve-all" && req.method === "POST") {
          alerts.forEach((a) => {
            a.resolved = true;
            a.resolved_at = (/* @__PURE__ */ new Date()).toISOString();
          });
          return send(res, 200, { message: "Todas resueltas" });
        }
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
        if (p === "/profile") {
          return send(res, 200, { data: { id: 1, name: "Admin Demo", email: "demo@sinoa.local", is_admin: true, created_at: "2025-01-15T10:00:00Z" } });
        }
        if (p === "/metrics") {
          return send(res, 200, { data: { total_sensors: all.length, total_devices: devices.length, active_alerts: alerts.filter((a) => !a.resolved).length, total_labs: labs.length, readings_today: 4287, uptime_percent: 98.3, online_devices: devices.filter((d) => d.status === "online").length, offline_devices: devices.filter((d) => d.status === "offline").length, total_alert_rules: alertRules.length, enabled_rules: alertRules.filter((r) => r.enabled).length } });
        }
        return send(res, 404, {
          message: "Esta vista no forma parte de la demostraci\xF3n local."
        });
      });
      const wss = new WebSocketServer({ noServer: true });
      const onUpgrade = (req, socket, head) => {
        if (req.url.startsWith("/app/"))
          wss.handleUpgrade(
            req,
            socket,
            head,
            (ws) => wss.emit("connection", ws)
          );
      };
      server.httpServer.on("upgrade", onUpgrade);
      wss.on("connection", (ws) => {
        ws.channels = /* @__PURE__ */ new Set();
        ws.sid = `${Date.now()}.${Math.floor(Math.random() * 1e5)}`;
        ws.send(
          JSON.stringify({
            event: "pusher:connection_established",
            data: JSON.stringify({
              socket_id: ws.sid,
              activity_timeout: 120
            })
          })
        );
        ws.on("message", (raw) => {
          try {
            const m = JSON.parse(raw);
            if (m.event === "pusher:ping")
              ws.send(
                JSON.stringify({
                  event: "pusher:pong",
                  data: "{}"
                })
              );
            if (m.event === "pusher:subscribe") {
              const ch = m.data.channel;
              if (ch.startsWith("private-") && !socketAuth.has(`${ws.sid}:${ch}`))
                return;
              ws.channels.add(ch);
              ws.send(
                JSON.stringify({
                  event: "pusher_internal:subscription_succeeded",
                  channel: ch,
                  data: "{}"
                })
              );
            }
            if (m.event === "pusher:unsubscribe")
              ws.channels.delete(m.data.channel);
          } catch {
          }
        });
      });
      const timer = setInterval(() => {
        const t = Math.floor(Date.now() / 2e3) * 2e3;
        for (const ws of wss.clients) {
          for (const ch of ws.channels) {
            if (ch.startsWith("private-") && !socketAuth.has(`${ws.sid}:${ch}`))
              continue;
            const id = Number(
              ch.match(/^(?:private-)?sensor\.(\d+)$/)?.[1]
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
                  reading_time: new Date(t).toISOString()
                })
              })
            );
          }
        }
      }, 2e3);
      server.httpServer.once("close", () => {
        clearInterval(timer);
        wss.close();
        server.httpServer.off("upgrade", onUpgrade);
      });
    }
  };
}

// vite.config.js
import { fileURLToPath, URL as URL2 } from "node:url";
import vue from "file:///C:/Users/jvrincon/Documents/iot_platform/iot-platform-v2/front/node_modules/@vitejs/plugin-vue/dist/index.mjs";
import { defineConfig, loadEnv } from "file:///C:/Users/jvrincon/Documents/iot_platform/iot-platform-v2/front/node_modules/vite/dist/node/index.js";
var __vite_injected_original_import_meta_url = "file:///C:/Users/jvrincon/Documents/iot_platform/iot-platform-v2/front/vite.config.js";
var vite_config_default = defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), "");
  return {
    // Normal development and the demo may run together. Their different
    // defines must not overwrite each other's optimized dependencies.
    cacheDir: `node_modules/.vite-${mode}`,
    define: mode === "demo" ? {
      "import.meta.env.VITE_PUSHER_APP_KEY": JSON.stringify("local-preview"),
      "import.meta.env.VITE_PUSHER_HOST": JSON.stringify("127.0.0.1"),
      "import.meta.env.VITE_PUSHER_PORT": JSON.stringify("5173"),
      "import.meta.env.VITE_PUSHER_FORCE_TLS": JSON.stringify("false"),
      "import.meta.env.VITE_PUSHER_SCHEME": JSON.stringify("http"),
      "import.meta.env.VITE_API_BASE_URL": JSON.stringify("/api")
    } : {},
    plugins: [vue(), ...mode === "demo" ? [labDemoPlugin()] : []],
    resolve: {
      alias: {
        "@": fileURLToPath(new URL2("./src", __vite_injected_original_import_meta_url))
      }
    },
    server: {
      host: "127.0.0.1",
      port: 5173,
      strictPort: true,
      proxy: {
        "/api": {
          target: env.VITE_API_PROXY_TARGET || "http://localhost:8000",
          changeOrigin: true
        }
      }
    }
  };
});
export {
  vite_config_default as default
};
//# sourceMappingURL=data:application/json;base64,ewogICJ2ZXJzaW9uIjogMywKICAic291cmNlcyI6IFsic2NyaXB0cy9kZW1vLXNlcnZlci5tanMiLCAidml0ZS5jb25maWcuanMiXSwKICAic291cmNlc0NvbnRlbnQiOiBbImNvbnN0IF9fdml0ZV9pbmplY3RlZF9vcmlnaW5hbF9kaXJuYW1lID0gXCJDOlxcXFxVc2Vyc1xcXFxqdnJpbmNvblxcXFxEb2N1bWVudHNcXFxcaW90X3BsYXRmb3JtXFxcXGlvdC1wbGF0Zm9ybS12MlxcXFxmcm9udFxcXFxzY3JpcHRzXCI7Y29uc3QgX192aXRlX2luamVjdGVkX29yaWdpbmFsX2ZpbGVuYW1lID0gXCJDOlxcXFxVc2Vyc1xcXFxqdnJpbmNvblxcXFxEb2N1bWVudHNcXFxcaW90X3BsYXRmb3JtXFxcXGlvdC1wbGF0Zm9ybS12MlxcXFxmcm9udFxcXFxzY3JpcHRzXFxcXGRlbW8tc2VydmVyLm1qc1wiO2NvbnN0IF9fdml0ZV9pbmplY3RlZF9vcmlnaW5hbF9pbXBvcnRfbWV0YV91cmwgPSBcImZpbGU6Ly8vQzovVXNlcnMvanZyaW5jb24vRG9jdW1lbnRzL2lvdF9wbGF0Zm9ybS9pb3QtcGxhdGZvcm0tdjIvZnJvbnQvc2NyaXB0cy9kZW1vLXNlcnZlci5tanNcIjsvLyBEZXZlbG9wbWVudC1vbmx5IGZpeHR1cmUgc2VydmljZS4gTmV2ZXIgaW1wb3J0ZWQgaW4gYSBub3JtYWwgYnVpbGQgb3IgcHJvZHVjdGlvbiBzZXJ2ZXIuXG4vLyBVc2VzIHJlYWwgZnJvbnRlbmQgSFRUUCBjb250cmFjdHMgYW5kIGEgbWluaW1hbCBQdXNoZXItY29tcGF0aWJsZSBXZWJTb2NrZXQgcHJvdG9jb2wuXG5pbXBvcnQgeyBXZWJTb2NrZXRTZXJ2ZXIgfSBmcm9tIFwid3NcIjtcbmltcG9ydCB7IHJhbmRvbVVVSUQgfSBmcm9tIFwibm9kZTpjcnlwdG9cIjtcbmltcG9ydCB7IHJlYWRGaWxlU3luYywgd3JpdGVGaWxlU3luYywgbWtkaXJTeW5jIH0gZnJvbSBcIm5vZGU6ZnNcIjtcbmV4cG9ydCBmdW5jdGlvbiBsYWJEZW1vUGx1Z2luKCkge1xuICAgIHJldHVybiB7XG4gICAgICAgIG5hbWU6IFwic2lub2EtbG9jYWwtZGVtb1wiLFxuICAgICAgICBjb25maWd1cmVTZXJ2ZXIoc2VydmVyKSB7XG4gICAgICAgICAgICBjb25zdCBkZXZpY2VzID0gW1xuICAgICAgICAgICAgICAgIHtcbiAgICAgICAgICAgICAgICAgICAgaWQ6IDEsXG4gICAgICAgICAgICAgICAgICAgIG5hbWU6IFwiUmVhY3RvciAxXCIsXG4gICAgICAgICAgICAgICAgICAgIGxvY2F0aW9uOiBcIlBsYW50YSBCYWphIC0gWm9uYSBBXCIsXG4gICAgICAgICAgICAgICAgICAgIHNlcmlhbF9udW1iZXI6IFwiU1ItUkVBQ1QtMDAxXCIsXG4gICAgICAgICAgICAgICAgICAgIGZpcm13YXJlX3ZlcnNpb246IFwiMy4yLjFcIixcbiAgICAgICAgICAgICAgICAgICAgaXBfYWRkcmVzczogXCIxOTIuMTY4LjEuMTAxXCIsXG4gICAgICAgICAgICAgICAgICAgIGxhYl9pZDogMSxcbiAgICAgICAgICAgICAgICAgICAgZGV2aWNlX3R5cGVfaWQ6IDEsXG4gICAgICAgICAgICAgICAgICAgIHN0YXR1czogXCJvbmxpbmVcIixcbiAgICAgICAgICAgICAgICAgICAgbGFzdF9zZWVuOiBuZXcgRGF0ZShlcG9jaCAtIDMwMDAwKS50b0lTT1N0cmluZygpLFxuICAgICAgICAgICAgICAgICAgICBpbnN0YWxsX2RhdGU6IFwiMjAyNC0wNi0xNVwiLFxuICAgICAgICAgICAgICAgICAgICBkZXNjcmlwdGlvbjogXCJSZWFjdG9yIGJpb2xcdTAwRjNnaWNvIHByaW5jaXBhbCBkZSBveGlnZW5hY2lcdTAwRjNuXCIsXG4gICAgICAgICAgICAgICAgICAgIHNlbnNvcnM6IFtcbiAgICAgICAgICAgICAgICAgICAgICAgIHsgaWQ6IDEsIG5hbWU6IFwiVGVtcGVyYXR1cmFcIiwgdW5pdDogXCJcdTAwQjBDXCIsIGRlc2NyaXB0aW9uOiBcIlRlbXBlcmF0dXJhIGRlbCByZWFjdG9yXCIsIHNlbnNvcl90eXBlX2lkOiAxIH0sXG4gICAgICAgICAgICAgICAgICAgICAgICB7IGlkOiAyLCBuYW1lOiBcIlByZXNpXHUwMEYzblwiLCB1bml0OiBcImJhclwiLCBkZXNjcmlwdGlvbjogXCJQcmVzaVx1MDBGM24gaW50ZXJuYSBkZWwgcmVhY3RvclwiLCBzZW5zb3JfdHlwZV9pZDogMiB9LFxuICAgICAgICAgICAgICAgICAgICAgICAgeyBpZDogMTEsIG5hbWU6IFwiT0QgUmVhY3RvciAxXCIsIHVuaXQ6IFwibWcvTFwiLCBkZXNjcmlwdGlvbjogXCJPeFx1MDBFRGdlbm8gZGlzdWVsdG8gZW4gcmVhY3RvclwiLCBzZW5zb3JfdHlwZV9pZDogNiB9LFxuICAgICAgICAgICAgICAgICAgICBdLFxuICAgICAgICAgICAgICAgIH0sXG4gICAgICAgICAgICAgICAge1xuICAgICAgICAgICAgICAgICAgICBpZDogMixcbiAgICAgICAgICAgICAgICAgICAgbmFtZTogXCJDXHUwMEUxbWFyYSBBXCIsXG4gICAgICAgICAgICAgICAgICAgIGxvY2F0aW9uOiBcIlBsYW50YSBCYWphIC0gWm9uYSBCXCIsXG4gICAgICAgICAgICAgICAgICAgIHNlcmlhbF9udW1iZXI6IFwiU1ItQ0FNLTAwMlwiLFxuICAgICAgICAgICAgICAgICAgICBmaXJtd2FyZV92ZXJzaW9uOiBcIjMuMS4wXCIsXG4gICAgICAgICAgICAgICAgICAgIGlwX2FkZHJlc3M6IFwiMTkyLjE2OC4xLjEwMlwiLFxuICAgICAgICAgICAgICAgICAgICBsYWJfaWQ6IDEsXG4gICAgICAgICAgICAgICAgICAgIGRldmljZV90eXBlX2lkOiAyLFxuICAgICAgICAgICAgICAgICAgICBzdGF0dXM6IFwib25saW5lXCIsXG4gICAgICAgICAgICAgICAgICAgIGxhc3Rfc2VlbjogbmV3IERhdGUoZXBvY2ggLSA0NTAwMCkudG9JU09TdHJpbmcoKSxcbiAgICAgICAgICAgICAgICAgICAgaW5zdGFsbF9kYXRlOiBcIjIwMjQtMDctMjBcIixcbiAgICAgICAgICAgICAgICAgICAgZGVzY3JpcHRpb246IFwiQ1x1MDBFMW1hcmEgZGUgY29udHJvbCBhbWJpZW50YWxcIixcbiAgICAgICAgICAgICAgICAgICAgc2Vuc29yczogW1xuICAgICAgICAgICAgICAgICAgICAgICAgeyBpZDogMywgbmFtZTogXCJIdW1lZGFkXCIsIHVuaXQ6IFwiJVwiLCBkZXNjcmlwdGlvbjogXCJIdW1lZGFkIHJlbGF0aXZhIGFtYmllbnRlXCIsIHNlbnNvcl90eXBlX2lkOiAzIH0sXG4gICAgICAgICAgICAgICAgICAgICAgICB7IGlkOiAxMiwgbmFtZTogXCJwSCBDXHUwMEUxbWFyYSBBXCIsIHVuaXQ6IFwicEhcIiwgZGVzY3JpcHRpb246IFwicEggZGUgbGEgc29sdWNpXHUwMEYzbiBkZSBsYXZhZG9cIiwgc2Vuc29yX3R5cGVfaWQ6IDUgfSxcbiAgICAgICAgICAgICAgICAgICAgXSxcbiAgICAgICAgICAgICAgICB9LFxuICAgICAgICAgICAgICAgIHtcbiAgICAgICAgICAgICAgICAgICAgaWQ6IDMsXG4gICAgICAgICAgICAgICAgICAgIG5hbWU6IFwiVGFucXVlIEJcIixcbiAgICAgICAgICAgICAgICAgICAgbG9jYXRpb246IFwiUGxhbnRhIEFsdGEgLSBab25hIENcIixcbiAgICAgICAgICAgICAgICAgICAgc2VyaWFsX251bWJlcjogXCJTUi1UQU5LLTAwM1wiLFxuICAgICAgICAgICAgICAgICAgICBmaXJtd2FyZV92ZXJzaW9uOiBcIjMuMi4xXCIsXG4gICAgICAgICAgICAgICAgICAgIGlwX2FkZHJlc3M6IFwiMTkyLjE2OC4xLjEwM1wiLFxuICAgICAgICAgICAgICAgICAgICBsYWJfaWQ6IDIsXG4gICAgICAgICAgICAgICAgICAgIGRldmljZV90eXBlX2lkOiAyLFxuICAgICAgICAgICAgICAgICAgICBzdGF0dXM6IFwib25saW5lXCIsXG4gICAgICAgICAgICAgICAgICAgIGxhc3Rfc2VlbjogbmV3IERhdGUoZXBvY2ggLSAxMjAwMDApLnRvSVNPU3RyaW5nKCksXG4gICAgICAgICAgICAgICAgICAgIGluc3RhbGxfZGF0ZTogXCIyMDI0LTAzLTEwXCIsXG4gICAgICAgICAgICAgICAgICAgIGRlc2NyaXB0aW9uOiBcIlRhbnF1ZSBkZSBidWZmZXIgZGUgcHJvY2Vzb1wiLFxuICAgICAgICAgICAgICAgICAgICBzZW5zb3JzOiBbXG4gICAgICAgICAgICAgICAgICAgICAgICB7IGlkOiA0LCBuYW1lOiBcIk5pdmVsXCIsIHVuaXQ6IFwiJVwiLCBkZXNjcmlwdGlvbjogXCJOaXZlbCBkZSBsbGVuYWRvIGRlbCB0YW5xdWVcIiwgc2Vuc29yX3R5cGVfaWQ6IDQgfSxcbiAgICAgICAgICAgICAgICAgICAgICAgIHsgaWQ6IDEzLCBuYW1lOiBcIkNvbmR1Y3RpdmlkYWQgQlwiLCB1bml0OiBcIlx1MDNCQ1MvY21cIiwgZGVzY3JpcHRpb246IFwiQ29uZHVjdGl2aWRhZCBkZWwgYnVmZmVyXCIsIHNlbnNvcl90eXBlX2lkOiA3IH0sXG4gICAgICAgICAgICAgICAgICAgIF0sXG4gICAgICAgICAgICAgICAgfSxcbiAgICAgICAgICAgICAgICB7XG4gICAgICAgICAgICAgICAgICAgIGlkOiA0LFxuICAgICAgICAgICAgICAgICAgICBuYW1lOiBcIkVzdGFjaVx1MDBGM24gTWV0YSA0XCIsXG4gICAgICAgICAgICAgICAgICAgIGxvY2F0aW9uOiBcIkV4dGVyaW9yIC0gWm9uYSBEXCIsXG4gICAgICAgICAgICAgICAgICAgIHNlcmlhbF9udW1iZXI6IFwiU1ItTUVUQS0wMDRcIixcbiAgICAgICAgICAgICAgICAgICAgZmlybXdhcmVfdmVyc2lvbjogXCIzLjAuNVwiLFxuICAgICAgICAgICAgICAgICAgICBpcF9hZGRyZXNzOiBcIjE5Mi4xNjguMS4xMDRcIixcbiAgICAgICAgICAgICAgICAgICAgbGFiX2lkOiAzLFxuICAgICAgICAgICAgICAgICAgICBkZXZpY2VfdHlwZV9pZDogMSxcbiAgICAgICAgICAgICAgICAgICAgc3RhdHVzOiBcIm9ubGluZVwiLFxuICAgICAgICAgICAgICAgICAgICBsYXN0X3NlZW46IG5ldyBEYXRlKGVwb2NoIC0gNjAwMDApLnRvSVNPU3RyaW5nKCksXG4gICAgICAgICAgICAgICAgICAgIGluc3RhbGxfZGF0ZTogXCIyMDI1LTAxLTA1XCIsXG4gICAgICAgICAgICAgICAgICAgIGRlc2NyaXB0aW9uOiBcIkVzdGFjaVx1MDBGM24gZGUgbW9uaXRvcmVvIGV4dGVyaW9yIG11bHRpcGFyYW1cdTAwRTl0cmljYVwiLFxuICAgICAgICAgICAgICAgICAgICBzZW5zb3JzOiBbXG4gICAgICAgICAgICAgICAgICAgICAgICB7IGlkOiA1LCBuYW1lOiBcInBIIEV4dGVyaW9yXCIsIHVuaXQ6IFwicEhcIiwgZGVzY3JpcHRpb246IFwicEggZGVsIGVmbHVlbnRlXCIsIHNlbnNvcl90eXBlX2lkOiA1IH0sXG4gICAgICAgICAgICAgICAgICAgICAgICB7IGlkOiA2LCBuYW1lOiBcIk9EIEV4dGVyaW9yXCIsIHVuaXQ6IFwibWcvTFwiLCBkZXNjcmlwdGlvbjogXCJPeFx1MDBFRGdlbm8gZGlzdWVsdG8gZWZsdWVudGVcIiwgc2Vuc29yX3R5cGVfaWQ6IDYgfSxcbiAgICAgICAgICAgICAgICAgICAgICAgIHsgaWQ6IDE0LCBuYW1lOiBcIlR1cmJpZGV6IEV4dFwiLCB1bml0OiBcIk5UVVwiLCBkZXNjcmlwdGlvbjogXCJUdXJiaWRleiBkZWwgZWZsdWVudGVcIiwgc2Vuc29yX3R5cGVfaWQ6IDggfSxcbiAgICAgICAgICAgICAgICAgICAgXSxcbiAgICAgICAgICAgICAgICB9LFxuICAgICAgICAgICAgICAgIHtcbiAgICAgICAgICAgICAgICAgICAgaWQ6IDUsXG4gICAgICAgICAgICAgICAgICAgIG5hbWU6IFwiQm9tYmEgUHJpbmNpcGFsXCIsXG4gICAgICAgICAgICAgICAgICAgIGxvY2F0aW9uOiBcIlBsYW50YSBCYWphIC0gWm9uYSBFXCIsXG4gICAgICAgICAgICAgICAgICAgIHNlcmlhbF9udW1iZXI6IFwiU1ItUFVNUC0wMDVcIixcbiAgICAgICAgICAgICAgICAgICAgZmlybXdhcmVfdmVyc2lvbjogXCIzLjIuMVwiLFxuICAgICAgICAgICAgICAgICAgICBpcF9hZGRyZXNzOiBcIjE5Mi4xNjguMS4xMDVcIixcbiAgICAgICAgICAgICAgICAgICAgbGFiX2lkOiA0LFxuICAgICAgICAgICAgICAgICAgICBkZXZpY2VfdHlwZV9pZDogNCxcbiAgICAgICAgICAgICAgICAgICAgc3RhdHVzOiBcIm9ubGluZVwiLFxuICAgICAgICAgICAgICAgICAgICBsYXN0X3NlZW46IG5ldyBEYXRlKGVwb2NoIC0gMTUwMDApLnRvSVNPU3RyaW5nKCksXG4gICAgICAgICAgICAgICAgICAgIGluc3RhbGxfZGF0ZTogXCIyMDI0LTA5LTAxXCIsXG4gICAgICAgICAgICAgICAgICAgIGRlc2NyaXB0aW9uOiBcIkJvbWJhIGRlIHJlY2lyY3VsYWNpXHUwMEYzbiBwcmluY2lwYWxcIixcbiAgICAgICAgICAgICAgICAgICAgc2Vuc29yczogW1xuICAgICAgICAgICAgICAgICAgICAgICAgeyBpZDogNywgbmFtZTogXCJDYXVkYWwgQm9tYmFcIiwgdW5pdDogXCJML21pblwiLCBkZXNjcmlwdGlvbjogXCJDYXVkYWwgZGUgcmVjaXJjdWxhY2lcdTAwRjNuXCIsIHNlbnNvcl90eXBlX2lkOiAxMCB9LFxuICAgICAgICAgICAgICAgICAgICAgICAgeyBpZDogMTUsIG5hbWU6IFwiUHJlc2lcdTAwRjNuIEJvbWJhXCIsIHVuaXQ6IFwiYmFyXCIsIGRlc2NyaXB0aW9uOiBcIlByZXNpXHUwMEYzbiBkZSBzYWxpZGEgZGUgbGEgYm9tYmFcIiwgc2Vuc29yX3R5cGVfaWQ6IDIgfSxcbiAgICAgICAgICAgICAgICAgICAgXSxcbiAgICAgICAgICAgICAgICB9LFxuICAgICAgICAgICAgICAgIHtcbiAgICAgICAgICAgICAgICAgICAgaWQ6IDYsXG4gICAgICAgICAgICAgICAgICAgIG5hbWU6IFwiQ2xhcmlmaWNhZG9yXCIsXG4gICAgICAgICAgICAgICAgICAgIGxvY2F0aW9uOiBcIlBsYW50YSBCYWphIC0gWm9uYSBGXCIsXG4gICAgICAgICAgICAgICAgICAgIHNlcmlhbF9udW1iZXI6IFwiU1ItQ0xBUi0wMDZcIixcbiAgICAgICAgICAgICAgICAgICAgZmlybXdhcmVfdmVyc2lvbjogXCIzLjEuOFwiLFxuICAgICAgICAgICAgICAgICAgICBpcF9hZGRyZXNzOiBcIjE5Mi4xNjguMS4xMDZcIixcbiAgICAgICAgICAgICAgICAgICAgbGFiX2lkOiA0LFxuICAgICAgICAgICAgICAgICAgICBkZXZpY2VfdHlwZV9pZDogMixcbiAgICAgICAgICAgICAgICAgICAgc3RhdHVzOiBcIm9mZmxpbmVcIixcbiAgICAgICAgICAgICAgICAgICAgbGFzdF9zZWVuOiBuZXcgRGF0ZShlcG9jaCAtIDM2MDAwMDApLnRvSVNPU3RyaW5nKCksXG4gICAgICAgICAgICAgICAgICAgIGluc3RhbGxfZGF0ZTogXCIyMDI0LTA0LTIyXCIsXG4gICAgICAgICAgICAgICAgICAgIGRlc2NyaXB0aW9uOiBcIkNsYXJpZmljYWRvciBkZSBzZWRpbWVudGFjaVx1MDBGM24gc2VjdW5kYXJpYVwiLFxuICAgICAgICAgICAgICAgICAgICBzZW5zb3JzOiBbXG4gICAgICAgICAgICAgICAgICAgICAgICB7IGlkOiA4LCBuYW1lOiBcIlR1cmJpZGV6IENsYXJpZi5cIiwgdW5pdDogXCJOVFVcIiwgZGVzY3JpcHRpb246IFwiVHVyYmlkZXogZGVsIGNsYXJpZmljYWRvclwiLCBzZW5zb3JfdHlwZV9pZDogOCB9LFxuICAgICAgICAgICAgICAgICAgICAgICAgeyBpZDogMTYsIG5hbWU6IFwiTml2ZWwgQ2xhcmlmLlwiLCB1bml0OiBcIiVcIiwgZGVzY3JpcHRpb246IFwiTml2ZWwgZGUgbG9kb3MgZGVsIGNsYXJpZmljYWRvclwiLCBzZW5zb3JfdHlwZV9pZDogNCB9LFxuICAgICAgICAgICAgICAgICAgICBdLFxuICAgICAgICAgICAgICAgIH0sXG4gICAgICAgICAgICAgICAge1xuICAgICAgICAgICAgICAgICAgICBpZDogNyxcbiAgICAgICAgICAgICAgICAgICAgbmFtZTogXCJOb2RvIDdcIixcbiAgICAgICAgICAgICAgICAgICAgbG9jYXRpb246IFwiUGxhbnRhIEFsdGEgLSBab25hIEdcIixcbiAgICAgICAgICAgICAgICAgICAgc2VyaWFsX251bWJlcjogXCJTUi1OT0RFLTAwN1wiLFxuICAgICAgICAgICAgICAgICAgICBmaXJtd2FyZV92ZXJzaW9uOiBcIjMuMi4wXCIsXG4gICAgICAgICAgICAgICAgICAgIGlwX2FkZHJlc3M6IFwiMTkyLjE2OC4xLjEwN1wiLFxuICAgICAgICAgICAgICAgICAgICBsYWJfaWQ6IDUsXG4gICAgICAgICAgICAgICAgICAgIGRldmljZV90eXBlX2lkOiAxLFxuICAgICAgICAgICAgICAgICAgICBzdGF0dXM6IFwib25saW5lXCIsXG4gICAgICAgICAgICAgICAgICAgIGxhc3Rfc2VlbjogbmV3IERhdGUoZXBvY2ggLSAyMDAwMCkudG9JU09TdHJpbmcoKSxcbiAgICAgICAgICAgICAgICAgICAgaW5zdGFsbF9kYXRlOiBcIjIwMjUtMDItMTRcIixcbiAgICAgICAgICAgICAgICAgICAgZGVzY3JpcHRpb246IFwiTm9kbyBkZSBhblx1MDBFMWxpc2lzIGVuIGxhYm9yYXRvcmlvXCIsXG4gICAgICAgICAgICAgICAgICAgIHNlbnNvcnM6IFtcbiAgICAgICAgICAgICAgICAgICAgICAgIHsgaWQ6IDksIG5hbWU6IFwiT1JQIExhYm9yYXRvcmlvXCIsIHVuaXQ6IFwibVZcIiwgZGVzY3JpcHRpb246IFwiUG90ZW5jaWFsIGRlIG94aWRhY2lcdTAwRjNuLXJlZHVjY2lcdTAwRjNuXCIsIHNlbnNvcl90eXBlX2lkOiA5IH0sXG4gICAgICAgICAgICAgICAgICAgICAgICB7IGlkOiAxNywgbmFtZTogXCJDb25kdWN0aXZpZGFkIExhYlwiLCB1bml0OiBcIlx1MDNCQ1MvY21cIiwgZGVzY3JpcHRpb246IFwiQ29uZHVjdGl2aWRhZCBkZSBtdWVzdHJhXCIsIHNlbnNvcl90eXBlX2lkOiA3IH0sXG4gICAgICAgICAgICAgICAgICAgIF0sXG4gICAgICAgICAgICAgICAgfSxcbiAgICAgICAgICAgICAgICB7XG4gICAgICAgICAgICAgICAgICAgIGlkOiA4LFxuICAgICAgICAgICAgICAgICAgICBuYW1lOiBcIkVzdGFjaVx1MDBGM24gRWZsdWVudGVcIixcbiAgICAgICAgICAgICAgICAgICAgbG9jYXRpb246IFwiRXh0ZXJpb3IgLSBab25hIEhcIixcbiAgICAgICAgICAgICAgICAgICAgc2VyaWFsX251bWJlcjogXCJTUi1FRkYtMDA4XCIsXG4gICAgICAgICAgICAgICAgICAgIGZpcm13YXJlX3ZlcnNpb246IFwiMy4yLjFcIixcbiAgICAgICAgICAgICAgICAgICAgaXBfYWRkcmVzczogXCIxOTIuMTY4LjEuMTA4XCIsXG4gICAgICAgICAgICAgICAgICAgIGxhYl9pZDogMyxcbiAgICAgICAgICAgICAgICAgICAgZGV2aWNlX3R5cGVfaWQ6IDEsXG4gICAgICAgICAgICAgICAgICAgIHN0YXR1czogXCJvbmxpbmVcIixcbiAgICAgICAgICAgICAgICAgICAgbGFzdF9zZWVuOiBuZXcgRGF0ZShlcG9jaCAtIDEwMDAwKS50b0lTT1N0cmluZygpLFxuICAgICAgICAgICAgICAgICAgICBpbnN0YWxsX2RhdGU6IFwiMjAyNS0wMy0wMVwiLFxuICAgICAgICAgICAgICAgICAgICBkZXNjcmlwdGlvbjogXCJFc3RhY2lcdTAwRjNuIGRlIG1vbml0b3JlbyBkZSBlZmx1ZW50ZSBmaW5hbFwiLFxuICAgICAgICAgICAgICAgICAgICBzZW5zb3JzOiBbXG4gICAgICAgICAgICAgICAgICAgICAgICB7IGlkOiAxMCwgbmFtZTogXCJwSCBFZmx1ZW50ZVwiLCB1bml0OiBcInBIXCIsIGRlc2NyaXB0aW9uOiBcInBIIGRlbCBlZmx1ZW50ZSB0cmF0YWRvXCIsIHNlbnNvcl90eXBlX2lkOiA1IH0sXG4gICAgICAgICAgICAgICAgICAgICAgICB7IGlkOiAxOCwgbmFtZTogXCJDYXVkYWwgRWZsdWVudGVcIiwgdW5pdDogXCJML21pblwiLCBkZXNjcmlwdGlvbjogXCJDYXVkYWwgZGUgZGVzY2FyZ2FcIiwgc2Vuc29yX3R5cGVfaWQ6IDEwIH0sXG4gICAgICAgICAgICAgICAgICAgICAgICB7IGlkOiAxOSwgbmFtZTogXCJPRCBFZmx1ZW50ZVwiLCB1bml0OiBcIm1nL0xcIiwgZGVzY3JpcHRpb246IFwiT3hcdTAwRURnZW5vIGRpc3VlbHRvIGVmbHVlbnRlIGZpbmFsXCIsIHNlbnNvcl90eXBlX2lkOiA2IH0sXG4gICAgICAgICAgICAgICAgICAgIF0sXG4gICAgICAgICAgICAgICAgfSxcbiAgICAgICAgICAgIF07XG4gICAgICAgICAgICAvLyBTeW50aGV0aWMgZ3JhcGgtc2FmZSBjb25maWd1cmF0aW9uIGZvciB0aGUgbGFiZWxsZWQgZGVtbyBvbmx5LlxuICAgICAgICAgICAgLy8gVGhlIHByb2R1Y3Rpb24gYm9vdHN0cmFwIG9idGFpbnMgdGhlc2UgaW50ZXJ2YWxzIGZyb20gUnVsZVRvR3JhcGhab25lcy5cbiAgICAgICAgICAgIGNvbnN0IGRlbW9MaW1pdHMgPSB7XG4gICAgICAgICAgICAgICAgMTogWzIwLCAyOCwgMzBdLFxuICAgICAgICAgICAgICAgIDI6IFswLjgsIDEuMiwgMS40XSxcbiAgICAgICAgICAgICAgICAzOiBbNDAsIDcwLCA4MF0sXG4gICAgICAgICAgICAgICAgNDogWzIwLCA4MCwgOTBdLFxuICAgICAgICAgICAgICAgIDU6IFs2LCA4LjUsIDldLFxuICAgICAgICAgICAgICAgIDY6IFsyLCA0LCA1XSxcbiAgICAgICAgICAgICAgICA3OiBbNTAsIDE1MCwgMjUwXSxcbiAgICAgICAgICAgICAgICA4OiBbMTUsIDUwLCAxMDBdLFxuICAgICAgICAgICAgICAgIDk6IFsxMDAsIDI1MCwgNDAwXSxcbiAgICAgICAgICAgICAgICAxMDogWzEwMCwgMzAwLCA0NTBdLFxuICAgICAgICAgICAgICAgIDExOiBbNCwgNS41LCA3XSxcbiAgICAgICAgICAgICAgICAxMjogWzYuNSwgNy41LCA4LjVdLFxuICAgICAgICAgICAgICAgIDEzOiBbNTAwLCAxNTAwLCAzMDAwXSxcbiAgICAgICAgICAgICAgICAxNDogWzIwLCA2MCwgMTIwXSxcbiAgICAgICAgICAgICAgICAxNTogWzAuNiwgMS4wLCAxLjNdLFxuICAgICAgICAgICAgICAgIDE2OiBbMzAsIDYwLCA4NV0sXG4gICAgICAgICAgICAgICAgMTc6IFsyMDAsIDgwMCwgMjUwMF0sXG4gICAgICAgICAgICAgICAgMTg6IFs4MCwgMjAwLCA0MDBdLFxuICAgICAgICAgICAgICAgIDE5OiBbMywgNSwgNi41XSxcbiAgICAgICAgICAgIH07XG4gICAgICAgICAgICBmb3IgKGNvbnN0IGRldmljZSBvZiBkZXZpY2VzKSB7XG4gICAgICAgICAgICAgICAgZm9yIChjb25zdCBzZW5zb3Igb2YgZGV2aWNlLnNlbnNvcnMpIHtcbiAgICAgICAgICAgICAgICAgICAgY29uc3QgW21pbiwgd2FybmluZywgZGFuZ2VyXSA9IGRlbW9MaW1pdHNbc2Vuc29yLmlkXTtcbiAgICAgICAgICAgICAgICAgICAgc2Vuc29yLmJhbmRzID0gW1xuICAgICAgICAgICAgICAgICAgICAgICAgeyBmcm9tOiBudWxsLCB0bzogbWluLCBzZXZlcml0eTogXCJ3YXJuaW5nXCIgfSxcbiAgICAgICAgICAgICAgICAgICAgICAgIHsgZnJvbTogbWluLCB0bzogd2FybmluZywgc2V2ZXJpdHk6IFwibm9ybWFsXCIgfSxcbiAgICAgICAgICAgICAgICAgICAgICAgIHsgZnJvbTogd2FybmluZywgdG86IGRhbmdlciwgc2V2ZXJpdHk6IFwid2FybmluZ1wiIH0sXG4gICAgICAgICAgICAgICAgICAgICAgICB7IGZyb206IGRhbmdlciwgdG86IG51bGwsIHNldmVyaXR5OiBcImRhbmdlclwiIH0sXG4gICAgICAgICAgICAgICAgICAgIF07XG4gICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgfVxuICAgICAgICAgICAgY29uc3QgYWxsID0gZGV2aWNlcy5mbGF0TWFwKChkKSA9PlxuICAgICAgICAgICAgICAgIGQuc2Vuc29ycy5tYXAoKHMpID0+ICh7IC4uLnMsIGRldmljZTogZCB9KSksXG4gICAgICAgICAgICApO1xuICAgICAgICAgICAgY29uc3QgdG9rZW5zID0gbmV3IFNldCgpLFxuICAgICAgICAgICAgICAgIHNvY2tldEF1dGggPSBuZXcgTWFwKCk7XG4gICAgICAgICAgICBjb25zdCBlcG9jaCA9IERhdGUubm93KCk7XG4gICAgICAgICAgICBsZXQgbGF5b3V0ID0gbnVsbDtcbiAgICAgICAgICAgIG1rZGlyU3luYyhcIi5kZW1vLWRhdGFcIiwgeyByZWN1cnNpdmU6IHRydWUgfSk7XG4gICAgICAgICAgICB0cnkge1xuICAgICAgICAgICAgICAgIGxheW91dCA9IEpTT04ucGFyc2UoXG4gICAgICAgICAgICAgICAgICAgIHJlYWRGaWxlU3luYyhcIi5kZW1vLWRhdGEvd29ya3NwYWNlLmpzb25cIiwgXCJ1dGY4XCIpLFxuICAgICAgICAgICAgICAgICk7XG4gICAgICAgICAgICB9IGNhdGNoIHt9XG4gICAgICAgICAgICBjb25zdCB2YWx1ZSA9IChpZCwgdCkgPT4ge1xuICAgICAgICAgICAgICAgIGNvbnN0IHggPSB0IC8gMTAwMDA7XG4gICAgICAgICAgICAgICAgLy8gRWFjaCBzZW5zb3IgaGFzIGEgYmFzZSArIHR3byBoYXJtb25pYyBjb21wb25lbnRzIGZvciByZWFsaXN0aWMgb3NjaWxsYXRpb25cbiAgICAgICAgICAgICAgICBjb25zdCBiYXNlcyA9ICAgWzAsIDI3LjQsIDEuMDMsIDYzLjIsIDY4LjEsIDcuMiwgMy44LCAxODAsIDI1MCwgNS4yLCAzNTAsIDUuNSwgNy4wLCAxMjAwLCA0NSwgMC45NSwgNzAsIDE4MDAsIDE1MCwgNC4xLCAyODBdO1xuICAgICAgICAgICAgICAgIGNvbnN0IGFtcDEgPSAgICBbMCwgMC44LCAgMC4wNCwgMS40LCAgMS44LCAgMC4zLCAwLjUsIDMwLCAgNDAsICAwLjQsIDYwLCAgMC4yLCAwLjE1LCA0MDAsIDE1LCAgMC4wNSwgMjAsICAzMDAsICAzMCwgIDAuMywgNTBdO1xuICAgICAgICAgICAgICAgIGNvbnN0IGZyZXExID0gICBbMCwgMC4xOCwgMC4xOCwgMC4xOCwgMC4xOCwgMC4xNSwgMC4yMiwgMC4xMiwgMC4xMCwgMC4yMCwgMC4wOCwgMC4xNCwgMC4xNiwgMC4wNiwgMC4xMSwgMC4xOSwgMC4xMywgMC4wNywgMC4xNCwgMC4xNywgMC4wOV07XG4gICAgICAgICAgICAgICAgY29uc3QgYW1wMiA9ICAgIFswLCAwLjE0LCAwLjAwNywgMC4yMiwgMC4zLCAwLjA2LCAwLjA4LCA1LCA3LCAwLjA3LCAxMCwgMC4wNCwgMC4wMywgODAsIDMsIDAuMDEsIDQsIDYwLCA1LCAwLjA2LCAxMF07XG4gICAgICAgICAgICAgICAgY29uc3QgZnJlcTIgPSAgIFswLCAwLjg5LCAwLjg5LCAwLjg5LCAwLjg5LCAwLjc1LCAwLjk1LCAwLjYwLCAwLjUwLCAwLjgwLCAwLjQwLCAwLjg1LCAwLjkwLCAwLjMwLCAwLjU1LCAwLjg4LCAwLjcwLCAwLjM1LCAwLjY1LCAwLjgyLCAwLjQ1XTtcbiAgICAgICAgICAgICAgICBjb25zdCBpID0gTWF0aC5taW4oaWQsIGJhc2VzLmxlbmd0aCAtIDEpO1xuICAgICAgICAgICAgICAgIHJldHVybiBOdW1iZXIoXG4gICAgICAgICAgICAgICAgICAgIChiYXNlc1tpXSArIE1hdGguc2luKHggKiBmcmVxMVtpXSArIGlkKSAqIGFtcDFbaV0gKyBNYXRoLnNpbih4ICogZnJlcTJbaV0pICogYW1wMltpXSkudG9GaXhlZCgyKSxcbiAgICAgICAgICAgICAgICApO1xuICAgICAgICAgICAgfTtcbiAgICAgICAgICAgIGNvbnN0IGFsZXJ0cyA9IFtcbiAgICAgICAgICAgICAgICB7XG4gICAgICAgICAgICAgICAgICAgIGlkOiAxLFxuICAgICAgICAgICAgICAgICAgICByZXNvbHZlZDogZmFsc2UsXG4gICAgICAgICAgICAgICAgICAgIGFsZXJ0X3J1bGU6IHtcbiAgICAgICAgICAgICAgICAgICAgICAgIG1lc3NhZ2U6IFwiVGVtcGVyYXR1cmEgYWx0YVwiLFxuICAgICAgICAgICAgICAgICAgICAgICAgc2V2ZXJpdHk6IFwiZGFuZ2VyXCIsXG4gICAgICAgICAgICAgICAgICAgIH0sXG4gICAgICAgICAgICAgICAgICAgIGRldmljZTogeyBpZDogMSwgbmFtZTogXCJSZWFjdG9yIDFcIiB9LFxuICAgICAgICAgICAgICAgICAgICBzZW5zb3I6IHsgaWQ6IDEsIG5hbWU6IFwiVGVtcGVyYXR1cmFcIiwgdW5pdDogXCJcdTAwQjBDXCIgfSxcbiAgICAgICAgICAgICAgICAgICAgY3JlYXRlZF9hdDogbmV3IERhdGUoZXBvY2ggLSAxODAwMDApLnRvSVNPU3RyaW5nKCksXG4gICAgICAgICAgICAgICAgICAgIGFja25vd2xlZGdlZF9ieTogbnVsbCxcbiAgICAgICAgICAgICAgICAgICAgcmVhZGluZ192YWx1ZTogMzEuMixcbiAgICAgICAgICAgICAgICB9LFxuICAgICAgICAgICAgICAgIHtcbiAgICAgICAgICAgICAgICAgICAgaWQ6IDIsXG4gICAgICAgICAgICAgICAgICAgIHJlc29sdmVkOiBmYWxzZSxcbiAgICAgICAgICAgICAgICAgICAgYWxlcnRfcnVsZToge1xuICAgICAgICAgICAgICAgICAgICAgICAgbWVzc2FnZTogXCJQcmVzaVx1MDBGM24gZnVlcmEgZGUgcmFuZ29cIixcbiAgICAgICAgICAgICAgICAgICAgICAgIHNldmVyaXR5OiBcIndhcm5pbmdcIixcbiAgICAgICAgICAgICAgICAgICAgfSxcbiAgICAgICAgICAgICAgICAgICAgZGV2aWNlOiB7IGlkOiAzLCBuYW1lOiBcIlRhbnF1ZSBCXCIgfSxcbiAgICAgICAgICAgICAgICAgICAgc2Vuc29yOiB7IGlkOiAyLCBuYW1lOiBcIlByZXNpXHUwMEYzblwiLCB1bml0OiBcImJhclwiIH0sXG4gICAgICAgICAgICAgICAgICAgIGNyZWF0ZWRfYXQ6IG5ldyBEYXRlKGVwb2NoIC0gNzIwMDAwKS50b0lTT1N0cmluZygpLFxuICAgICAgICAgICAgICAgICAgICBhY2tub3dsZWRnZWRfYnk6IG51bGwsXG4gICAgICAgICAgICAgICAgICAgIHJlYWRpbmdfdmFsdWU6IDEuNDUsXG4gICAgICAgICAgICAgICAgfSxcbiAgICAgICAgICAgICAgICB7XG4gICAgICAgICAgICAgICAgICAgIGlkOiAzLFxuICAgICAgICAgICAgICAgICAgICByZXNvbHZlZDogZmFsc2UsXG4gICAgICAgICAgICAgICAgICAgIGFsZXJ0X3J1bGU6IHtcbiAgICAgICAgICAgICAgICAgICAgICAgIG1lc3NhZ2U6IFwicEggYmFqbyBlbiBlZmx1ZW50ZVwiLFxuICAgICAgICAgICAgICAgICAgICAgICAgc2V2ZXJpdHk6IFwiZGFuZ2VyXCIsXG4gICAgICAgICAgICAgICAgICAgIH0sXG4gICAgICAgICAgICAgICAgICAgIGRldmljZTogeyBpZDogOCwgbmFtZTogXCJFc3RhY2lcdTAwRjNuIEVmbHVlbnRlXCIgfSxcbiAgICAgICAgICAgICAgICAgICAgc2Vuc29yOiB7IGlkOiAxMCwgbmFtZTogXCJwSCBFZmx1ZW50ZVwiLCB1bml0OiBcInBIXCIgfSxcbiAgICAgICAgICAgICAgICAgICAgY3JlYXRlZF9hdDogbmV3IERhdGUoZXBvY2ggLSAxMjAwMDAwKS50b0lTT1N0cmluZygpLFxuICAgICAgICAgICAgICAgICAgICBhY2tub3dsZWRnZWRfYnk6IFwiT3BlcmFkb3JcIixcbiAgICAgICAgICAgICAgICAgICAgcmVhZGluZ192YWx1ZTogNS44LFxuICAgICAgICAgICAgICAgIH0sXG4gICAgICAgICAgICAgICAge1xuICAgICAgICAgICAgICAgICAgICBpZDogNCxcbiAgICAgICAgICAgICAgICAgICAgcmVzb2x2ZWQ6IHRydWUsXG4gICAgICAgICAgICAgICAgICAgIHJlc29sdmVkX2F0OiBuZXcgRGF0ZShlcG9jaCAtIDM2MDAwMDApLnRvSVNPU3RyaW5nKCksXG4gICAgICAgICAgICAgICAgICAgIGFsZXJ0X3J1bGU6IHtcbiAgICAgICAgICAgICAgICAgICAgICAgIG1lc3NhZ2U6IFwiVHVyYmlkZXogZWxldmFkYVwiLFxuICAgICAgICAgICAgICAgICAgICAgICAgc2V2ZXJpdHk6IFwid2FybmluZ1wiLFxuICAgICAgICAgICAgICAgICAgICB9LFxuICAgICAgICAgICAgICAgICAgICBkZXZpY2U6IHsgaWQ6IDYsIG5hbWU6IFwiQ2xhcmlmaWNhZG9yXCIgfSxcbiAgICAgICAgICAgICAgICAgICAgc2Vuc29yOiB7IGlkOiA4LCBuYW1lOiBcIlR1cmJpZGV6IENsYXJpZi5cIiwgdW5pdDogXCJOVFVcIiB9LFxuICAgICAgICAgICAgICAgICAgICBjcmVhdGVkX2F0OiBuZXcgRGF0ZShlcG9jaCAtIDU0MDAwMDApLnRvSVNPU3RyaW5nKCksXG4gICAgICAgICAgICAgICAgICAgIGFja25vd2xlZGdlZF9ieTogXCJBZG1pbiBEZW1vXCIsXG4gICAgICAgICAgICAgICAgICAgIHJlYWRpbmdfdmFsdWU6IDU1LjMsXG4gICAgICAgICAgICAgICAgfSxcbiAgICAgICAgICAgICAgICB7XG4gICAgICAgICAgICAgICAgICAgIGlkOiA1LFxuICAgICAgICAgICAgICAgICAgICByZXNvbHZlZDogZmFsc2UsXG4gICAgICAgICAgICAgICAgICAgIGFsZXJ0X3J1bGU6IHtcbiAgICAgICAgICAgICAgICAgICAgICAgIG1lc3NhZ2U6IFwiT3hcdTAwRURnZW5vIGRpc3VlbHRvIGJham9cIixcbiAgICAgICAgICAgICAgICAgICAgICAgIHNldmVyaXR5OiBcImRhbmdlclwiLFxuICAgICAgICAgICAgICAgICAgICB9LFxuICAgICAgICAgICAgICAgICAgICBkZXZpY2U6IHsgaWQ6IDQsIG5hbWU6IFwiRXN0YWNpXHUwMEYzbiBNZXRhIDRcIiB9LFxuICAgICAgICAgICAgICAgICAgICBzZW5zb3I6IHsgaWQ6IDYsIG5hbWU6IFwiT0QgRXh0ZXJpb3JcIiwgdW5pdDogXCJtZy9MXCIgfSxcbiAgICAgICAgICAgICAgICAgICAgY3JlYXRlZF9hdDogbmV3IERhdGUoZXBvY2ggLSAzMDAwMDApLnRvSVNPU3RyaW5nKCksXG4gICAgICAgICAgICAgICAgICAgIGFja25vd2xlZGdlZF9ieTogbnVsbCxcbiAgICAgICAgICAgICAgICAgICAgcmVhZGluZ192YWx1ZTogMS44LFxuICAgICAgICAgICAgICAgIH0sXG4gICAgICAgICAgICAgICAge1xuICAgICAgICAgICAgICAgICAgICBpZDogNixcbiAgICAgICAgICAgICAgICAgICAgcmVzb2x2ZWQ6IHRydWUsXG4gICAgICAgICAgICAgICAgICAgIHJlc29sdmVkX2F0OiBuZXcgRGF0ZShlcG9jaCAtIDcyMDAwMDApLnRvSVNPU3RyaW5nKCksXG4gICAgICAgICAgICAgICAgICAgIGFsZXJ0X3J1bGU6IHtcbiAgICAgICAgICAgICAgICAgICAgICAgIG1lc3NhZ2U6IFwiQ2F1ZGFsIGJham8gZW4gYm9tYmFcIixcbiAgICAgICAgICAgICAgICAgICAgICAgIHNldmVyaXR5OiBcIndhcm5pbmdcIixcbiAgICAgICAgICAgICAgICAgICAgfSxcbiAgICAgICAgICAgICAgICAgICAgZGV2aWNlOiB7IGlkOiA1LCBuYW1lOiBcIkJvbWJhIFByaW5jaXBhbFwiIH0sXG4gICAgICAgICAgICAgICAgICAgIHNlbnNvcjogeyBpZDogNywgbmFtZTogXCJDYXVkYWwgQm9tYmFcIiwgdW5pdDogXCJML21pblwiIH0sXG4gICAgICAgICAgICAgICAgICAgIGNyZWF0ZWRfYXQ6IG5ldyBEYXRlKGVwb2NoIC0gOTAwMDAwMCkudG9JU09TdHJpbmcoKSxcbiAgICAgICAgICAgICAgICAgICAgYWNrbm93bGVkZ2VkX2J5OiBcIk9wZXJhZG9yXCIsXG4gICAgICAgICAgICAgICAgICAgIHJlYWRpbmdfdmFsdWU6IDQ1LjIsXG4gICAgICAgICAgICAgICAgfSxcbiAgICAgICAgICAgIF07XG4gICAgICAgICAgICBjb25zdCBsYWJzID0gW1xuICAgICAgICAgICAgICAgIHsgaWQ6IDEsIG5hbWU6IFwiUmVhY3RvciBCaW9sb2dpY28gQVwiLCBhcmVhOiBcIlRyYXRhbWllbnRvIFNlY3VuZGFyaW9cIiwgcHJvY2Vzc19saW5lOiBcIk94aWdlbmFjaW9uXCIsIGRlc2NyaXB0aW9uOiBcIlJlYWN0b3IgYWVyb2JpbyBwcmluY2lwYWxcIiwgY2FwYWNpdHlfbTM6IDUwMCwgb3BlcmF0b3I6IFwiSW5nLiBDYXJsb3MgTWVuZG96YVwiLCBzdGF0dXM6IFwiYWN0aXZlXCIgfSxcbiAgICAgICAgICAgICAgICB7IGlkOiAyLCBuYW1lOiBcIlJlYWN0b3IgQmlvbG9naWNvIEJcIiwgYXJlYTogXCJUcmF0YW1pZW50byBTZWN1bmRhcmlvXCIsIHByb2Nlc3NfbGluZTogXCJOaXRyaWZpY2FjaW9uXCIsIGRlc2NyaXB0aW9uOiBcIlJlYWN0b3IgZGUgc29wb3J0ZVwiLCBjYXBhY2l0eV9tMzogMzUwLCBvcGVyYXRvcjogXCJJbmcuIExhdXJhIFZlZ2FcIiwgc3RhdHVzOiBcImFjdGl2ZVwiIH0sXG4gICAgICAgICAgICAgICAgeyBpZDogMywgbmFtZTogXCJUYW5xdWUgZGUgQWp1c3RlIHBIXCIsIGFyZWE6IFwiUHJldHJhdGFtaWVudG9cIiwgcHJvY2Vzc19saW5lOiBcIk5ldXRyYWxpemFjaW9uXCIsIGRlc2NyaXB0aW9uOiBcIkFqdXN0ZSBkZSBwSCBkZSBlbnRyYWRhXCIsIGNhcGFjaXR5X20zOiAyMDAsIG9wZXJhdG9yOiBcIlRcdTAwRTljLiBSb2JlcnRvIFNpbHZhXCIsIHN0YXR1czogXCJhY3RpdmVcIiB9LFxuICAgICAgICAgICAgICAgIHsgaWQ6IDQsIG5hbWU6IFwiQ2xhcmlmaWNhZG9yXCIsIGFyZWE6IFwiU2VkaW1lbnRhY2lvblwiLCBwcm9jZXNzX2xpbmU6IFwiQ2xhcmlmaWNhY2lvblwiLCBkZXNjcmlwdGlvbjogXCJTZXBhcmFjaW9uIGRlIHNvbGlkb3NcIiwgY2FwYWNpdHlfbTM6IDgwMCwgb3BlcmF0b3I6IFwiSW5nLiBBbmEgVG9ycmVzXCIsIHN0YXR1czogXCJtYWludGVuYW5jZVwiIH0sXG4gICAgICAgICAgICAgICAgeyBpZDogNSwgbmFtZTogXCJMYWJvcmF0b3JpbyBkZSBDb250cm9sXCIsIGFyZWE6IFwiQW5hbGl0aWNhXCIsIHByb2Nlc3NfbGluZTogXCJNb25pdG9yZW9cIiwgZGVzY3JpcHRpb246IFwiVmFsaWRhY2lvbiBkZSBjYWxpZGFkXCIsIGNhcGFjaXR5X20zOiBudWxsLCBvcGVyYXRvcjogXCJCaW9sLiBNYXJcdTAwRURhIFJvamFzXCIsIHN0YXR1czogXCJhY3RpdmVcIiB9LFxuICAgICAgICAgICAgICAgIHsgaWQ6IDYsIG5hbWU6IFwiRXN0YWNpXHUwMEYzbiBkZSBCb21iZW9cIiwgYXJlYTogXCJIaWRyXHUwMEUxdWxpY2FcIiwgcHJvY2Vzc19saW5lOiBcIlJlY2lyY3VsYWNpXHUwMEYzblwiLCBkZXNjcmlwdGlvbjogXCJCb21iZW8gZGUgcmV0b3JubyBkZSBsb2Rvc1wiLCBjYXBhY2l0eV9tMzogMTAwLCBvcGVyYXRvcjogXCJUXHUwMEU5Yy4gSnVhbiBQXHUwMEU5cmV6XCIsIHN0YXR1czogXCJhY3RpdmVcIiB9LFxuICAgICAgICAgICAgICAgIHsgaWQ6IDcsIG5hbWU6IFwiRGVzaW5mZWNjaVx1MDBGM24gVVZcIiwgYXJlYTogXCJUcmF0YW1pZW50byBUZXJjaWFyaW9cIiwgcHJvY2Vzc19saW5lOiBcIkRlc2luZmVjY2lcdTAwRjNuXCIsIGRlc2NyaXB0aW9uOiBcIkVsaW1pbmFjaVx1MDBGM24gbWljcm9iaWFuYVwiLCBjYXBhY2l0eV9tMzogMTUwLCBvcGVyYXRvcjogXCJJbmcuIERpZWdvIExcdTAwRjNwZXpcIiwgc3RhdHVzOiBcImFjdGl2ZVwiIH0sXG4gICAgICAgICAgICBdO1xuICAgICAgICAgICAgY29uc3Qgc2Vuc29yVHlwZXMgPSBbXG4gICAgICAgICAgICAgICAgeyBpZDogMSwgbmFtZTogXCJUZW1wZXJhdHVyYVwiLCB1bml0OiBcIlx1MDBCMENcIiwgbWluX3ZhbHVlOiAwLCBtYXhfdmFsdWU6IDEwMCwgZGVzY3JpcHRpb246IFwiVGVtcGVyYXR1cmEgYW1iaWVudGUgbyBkZSBwcm9jZXNvXCIsIGljb246IFwidGhlcm1vbWV0ZXJcIiB9LFxuICAgICAgICAgICAgICAgIHsgaWQ6IDIsIG5hbWU6IFwiUHJlc2lcdTAwRjNuXCIsIHVuaXQ6IFwiYmFyXCIsIG1pbl92YWx1ZTogMCwgbWF4X3ZhbHVlOiAxMCwgZGVzY3JpcHRpb246IFwiUHJlc2lcdTAwRjNuIG1hbm9tXHUwMEU5dHJpY2FcIiwgaWNvbjogXCJnYXVnZVwiIH0sXG4gICAgICAgICAgICAgICAgeyBpZDogMywgbmFtZTogXCJIdW1lZGFkXCIsIHVuaXQ6IFwiJVwiLCBtaW5fdmFsdWU6IDAsIG1heF92YWx1ZTogMTAwLCBkZXNjcmlwdGlvbjogXCJIdW1lZGFkIHJlbGF0aXZhIGRlbCBhaXJlXCIsIGljb246IFwiZHJvcGxldFwiIH0sXG4gICAgICAgICAgICAgICAgeyBpZDogNCwgbmFtZTogXCJOaXZlbFwiLCB1bml0OiBcIiVcIiwgbWluX3ZhbHVlOiAwLCBtYXhfdmFsdWU6IDEwMCwgZGVzY3JpcHRpb246IFwiTml2ZWwgZGUgbGxlbmFkbyBkZSB0YW5xdWVcIiwgaWNvbjogXCJsZXZlbFwiIH0sXG4gICAgICAgICAgICAgICAgeyBpZDogNSwgbmFtZTogXCJwSFwiLCB1bml0OiBcInBIXCIsIG1pbl92YWx1ZTogMCwgbWF4X3ZhbHVlOiAxNCwgZGVzY3JpcHRpb246IFwiUG90ZW5jaWFsIGRlIGhpZHJcdTAwRjNnZW5vXCIsIGljb246IFwicGhcIiB9LFxuICAgICAgICAgICAgICAgIHsgaWQ6IDYsIG5hbWU6IFwiT3hcdTAwRURnZW5vIERpc3VlbHRvXCIsIHVuaXQ6IFwibWcvTFwiLCBtaW5fdmFsdWU6IDAsIG1heF92YWx1ZTogMjAsIGRlc2NyaXB0aW9uOiBcIkNvbmNlbnRyYWNpXHUwMEYzbiBkZSBveFx1MDBFRGdlbm8gZGlzdWVsdG9cIiwgaWNvbjogXCJveHlnZW5cIiB9LFxuICAgICAgICAgICAgICAgIHsgaWQ6IDcsIG5hbWU6IFwiQ29uZHVjdGl2aWRhZFwiLCB1bml0OiBcIlx1MDNCQ1MvY21cIiwgbWluX3ZhbHVlOiAwLCBtYXhfdmFsdWU6IDUwMDAsIGRlc2NyaXB0aW9uOiBcIkNhcGFjaWRhZCBkZSBjb25kdWNjaVx1MDBGM24gZWxcdTAwRTljdHJpY2FcIiwgaWNvbjogXCJjb25kdWN0aXZpdHlcIiB9LFxuICAgICAgICAgICAgICAgIHsgaWQ6IDgsIG5hbWU6IFwiVHVyYmlkZXpcIiwgdW5pdDogXCJOVFVcIiwgbWluX3ZhbHVlOiAwLCBtYXhfdmFsdWU6IDUwMCwgZGVzY3JpcHRpb246IFwiTml2ZWwgZGUgdHVyYmlkZXogZGVsIGFndWFcIiwgaWNvbjogXCJ0dXJiaWRpdHlcIiB9LFxuICAgICAgICAgICAgICAgIHsgaWQ6IDksIG5hbWU6IFwiT1JQXCIsIHVuaXQ6IFwibVZcIiwgbWluX3ZhbHVlOiAtNTAwLCBtYXhfdmFsdWU6IDUwMCwgZGVzY3JpcHRpb246IFwiUG90ZW5jaWFsIGRlIG94aWRhY2lcdTAwRjNuLXJlZHVjY2lcdTAwRjNuXCIsIGljb246IFwib3JwXCIgfSxcbiAgICAgICAgICAgICAgICB7IGlkOiAxMCwgbmFtZTogXCJDYXVkYWxcIiwgdW5pdDogXCJML21pblwiLCBtaW5fdmFsdWU6IDAsIG1heF92YWx1ZTogNTAwLCBkZXNjcmlwdGlvbjogXCJGbHVqbyB2b2x1bVx1MDBFOXRyaWNvXCIsIGljb246IFwiZmxvd1wiIH0sXG4gICAgICAgICAgICAgICAgeyBpZDogMTEsIG5hbWU6IFwiU1x1MDBGM2xpZG9zIERpc3VlbHRvc1wiLCB1bml0OiBcInBwbVwiLCBtaW5fdmFsdWU6IDAsIG1heF92YWx1ZTogMjAwMCwgZGVzY3JpcHRpb246IFwiVG90YWwgZGUgc1x1MDBGM2xpZG9zIGRpc3VlbHRvc1wiLCBpY29uOiBcInNvbGlkc1wiIH0sXG4gICAgICAgICAgICAgICAgeyBpZDogMTIsIG5hbWU6IFwiTml0clx1MDBGM2dlbm8gVG90YWxcIiwgdW5pdDogXCJtZy9MXCIsIG1pbl92YWx1ZTogMCwgbWF4X3ZhbHVlOiA1MCwgZGVzY3JpcHRpb246IFwiTml0clx1MDBGM2dlbm8gdG90YWwgS2plbGRhaGxcIiwgaWNvbjogXCJuaXRyb2dlblwiIH0sXG4gICAgICAgICAgICAgICAgeyBpZDogMTMsIG5hbWU6IFwiRlx1MDBGM3Nmb3JvIFRvdGFsXCIsIHVuaXQ6IFwibWcvTFwiLCBtaW5fdmFsdWU6IDAsIG1heF92YWx1ZTogMTAsIGRlc2NyaXB0aW9uOiBcIkZcdTAwRjNzZm9ybyB0b3RhbFwiLCBpY29uOiBcInBob3NwaG9ydXNcIiB9LFxuICAgICAgICAgICAgXTtcbiAgICAgICAgICAgIGNvbnN0IGRldmljZVR5cGVzID0gW1xuICAgICAgICAgICAgICAgIHsgaWQ6IDEsIG5hbWU6IFwiRXN0YWNpXHUwMEYzbiBkZSBNb25pdG9yZW9cIiwgZGVzY3JpcHRpb246IFwiTm9kbyBtdWx0aXBhcmFtXHUwMEU5dHJpY29cIiwgcHJvdG9jb2w6IFwiTVFUVFwiLCByZWZyZXNoX2ludGVydmFsX3M6IDUgfSxcbiAgICAgICAgICAgICAgICB7IGlkOiAyLCBuYW1lOiBcIkNvbnRyb2xhZG9yXCIsIGRlc2NyaXB0aW9uOiBcIkNvbnRyb2xhZG9yIGRlIHByb2Nlc29cIiwgcHJvdG9jb2w6IFwiTW9kYnVzIFRDUFwiLCByZWZyZXNoX2ludGVydmFsX3M6IDIgfSxcbiAgICAgICAgICAgICAgICB7IGlkOiAzLCBuYW1lOiBcIkFuYWxpemFkb3JcIiwgZGVzY3JpcHRpb246IFwiQW5hbGl6YWRvciBlbiBsXHUwMEVEbmVhXCIsIHByb3RvY29sOiBcIk1vZGJ1cyBSVFVcIiwgcmVmcmVzaF9pbnRlcnZhbF9zOiAxMCB9LFxuICAgICAgICAgICAgICAgIHsgaWQ6IDQsIG5hbWU6IFwiQm9tYmFcIiwgZGVzY3JpcHRpb246IFwiQm9tYmEgZGUgcmVjaXJjdWxhY2lcdTAwRjNuXCIsIHByb3RvY29sOiBcIk9QQy1VQVwiLCByZWZyZXNoX2ludGVydmFsX3M6IDEgfSxcbiAgICAgICAgICAgICAgICB7IGlkOiA1LCBuYW1lOiBcIlZcdTAwRTFsdnVsYVwiLCBkZXNjcmlwdGlvbjogXCJWXHUwMEUxbHZ1bGEgZGUgY29udHJvbFwiLCBwcm90b2NvbDogXCJIQVJUXCIsIHJlZnJlc2hfaW50ZXJ2YWxfczogMSB9LFxuICAgICAgICAgICAgICAgIHsgaWQ6IDYsIG5hbWU6IFwiU2Vuc29yIFB1cm9cIiwgZGVzY3JpcHRpb246IFwiU2Vuc29yIGRlIHVuIHNvbG8gcGFyXHUwMEUxbWV0cm9cIiwgcHJvdG9jb2w6IFwiNC0yMG1BXCIsIHJlZnJlc2hfaW50ZXJ2YWxfczogMyB9LFxuICAgICAgICAgICAgXTtcbiAgICAgICAgICAgIGNvbnN0IGRlbW9Vc2VycyA9IFtcbiAgICAgICAgICAgICAgICB7IGlkOiAxLCBuYW1lOiBcIkFkbWluIERlbW9cIiwgZW1haWw6IFwiZGVtb0BzaW5vYS5sb2NhbFwiLCBpc19hZG1pbjogdHJ1ZSwgcm9sZTogXCJBZG1pbmlzdHJhZG9yXCIsIGRlcGFydG1lbnQ6IFwiSW5nZW5pZXJcdTAwRURhXCIsIGNyZWF0ZWRfYXQ6IFwiMjAyNS0wMS0xNVQxMDowMDowMFpcIiB9LFxuICAgICAgICAgICAgICAgIHsgaWQ6IDIsIG5hbWU6IFwiT3BlcmFkb3JcIiwgZW1haWw6IFwib3BlcmFkb3JAc2lub2EubG9jYWxcIiwgaXNfYWRtaW46IGZhbHNlLCByb2xlOiBcIk9wZXJhZG9yXCIsIGRlcGFydG1lbnQ6IFwiT3BlcmFjaW9uZXNcIiwgY3JlYXRlZF9hdDogXCIyMDI1LTAyLTIwVDA4OjAwOjAwWlwiIH0sXG4gICAgICAgICAgICAgICAgeyBpZDogMywgbmFtZTogXCJWaXNvclwiLCBlbWFpbDogXCJ2aXNvckBzaW5vYS5sb2NhbFwiLCBpc19hZG1pbjogZmFsc2UsIHJvbGU6IFwiVmlzb3JcIiwgZGVwYXJ0bWVudDogXCJDYWxpZGFkXCIsIGNyZWF0ZWRfYXQ6IFwiMjAyNS0wMy0xMFQxNDowMDowMFpcIiB9LFxuICAgICAgICAgICAgICAgIHsgaWQ6IDQsIG5hbWU6IFwiSW5nLiBDYXJsb3MgTWVuZG96YVwiLCBlbWFpbDogXCJjYXJsb3NAc2lub2EubG9jYWxcIiwgaXNfYWRtaW46IGZhbHNlLCByb2xlOiBcIlN1cGVydmlzb3JcIiwgZGVwYXJ0bWVudDogXCJJbmdlbmllclx1MDBFRGFcIiwgY3JlYXRlZF9hdDogXCIyMDI1LTAxLTIwVDA5OjAwOjAwWlwiIH0sXG4gICAgICAgICAgICAgICAgeyBpZDogNSwgbmFtZTogXCJCaW9sLiBNYXJcdTAwRURhIFJvamFzXCIsIGVtYWlsOiBcIm1hcmlhQHNpbm9hLmxvY2FsXCIsIGlzX2FkbWluOiBmYWxzZSwgcm9sZTogXCJUXHUwMEU5Y25pY29cIiwgZGVwYXJ0bWVudDogXCJMYWJvcmF0b3Jpb1wiLCBjcmVhdGVkX2F0OiBcIjIwMjUtMDQtMDVUMTE6MDA6MDBaXCIgfSxcbiAgICAgICAgICAgIF07XG4gICAgICAgICAgICBjb25zdCBhbGVydFJ1bGVzID0gW1xuICAgICAgICAgICAgICAgIHsgaWQ6IDEsIG5hbWU6IFwiVGVtcCBhbHRhIFJlYWN0b3IgQVwiLCBzZW5zb3JfdHlwZV9pZDogMSwgZGV2aWNlX2lkOiAxLCBjb25kaXRpb246IFwiPlwiLCB0aHJlc2hvbGQ6IDI4LCBzZXZlcml0eTogXCJkYW5nZXJcIiwgZW5hYmxlZDogdHJ1ZSwgbWVzc2FnZTogXCJUZW1wZXJhdHVyYSBmdWVyYSBkZSByYW5nbyBhbHRvXCIsIGNvb2xkb3duX21pbnV0ZXM6IDUgfSxcbiAgICAgICAgICAgICAgICB7IGlkOiAyLCBuYW1lOiBcInBIIGJham8gVGFucXVlXCIsIHNlbnNvcl90eXBlX2lkOiA1LCBkZXZpY2VfaWQ6IDIsIGNvbmRpdGlvbjogXCI8XCIsIHRocmVzaG9sZDogNi41LCBzZXZlcml0eTogXCJ3YXJuaW5nXCIsIGVuYWJsZWQ6IHRydWUsIG1lc3NhZ2U6IFwicEggcG9yIGRlYmFqbyBkZWwgdW1icmFsXCIsIGNvb2xkb3duX21pbnV0ZXM6IDEwIH0sXG4gICAgICAgICAgICAgICAgeyBpZDogMywgbmFtZTogXCJUdXJiaWRleiBhbHRhIENsYXJpZi5cIiwgc2Vuc29yX3R5cGVfaWQ6IDgsIGRldmljZV9pZDogNiwgY29uZGl0aW9uOiBcIj5cIiwgdGhyZXNob2xkOiAxMDAsIHNldmVyaXR5OiBcImRhbmdlclwiLCBlbmFibGVkOiB0cnVlLCBtZXNzYWdlOiBcIlR1cmJpZGV6IGV4Y2VzaXZhIGVuIGNsYXJpZmljYWRvclwiLCBjb29sZG93bl9taW51dGVzOiA1IH0sXG4gICAgICAgICAgICAgICAgeyBpZDogNCwgbmFtZTogXCJPRCBiYWpvIFJlYWN0b3IgQVwiLCBzZW5zb3JfdHlwZV9pZDogNiwgZGV2aWNlX2lkOiAxLCBjb25kaXRpb246IFwiPFwiLCB0aHJlc2hvbGQ6IDIsIHNldmVyaXR5OiBcImRhbmdlclwiLCBlbmFibGVkOiBmYWxzZSwgbWVzc2FnZTogXCJPeFx1MDBFRGdlbm8gZGlzdWVsdG8gY3JcdTAwRUR0aWNvXCIsIGNvb2xkb3duX21pbnV0ZXM6IDE1IH0sXG4gICAgICAgICAgICAgICAgeyBpZDogNSwgbmFtZTogXCJQcmVzaVx1MDBGM24gYWx0YSBCb21iYVwiLCBzZW5zb3JfdHlwZV9pZDogMiwgZGV2aWNlX2lkOiA1LCBjb25kaXRpb246IFwiPlwiLCB0aHJlc2hvbGQ6IDEuMywgc2V2ZXJpdHk6IFwid2FybmluZ1wiLCBlbmFibGVkOiB0cnVlLCBtZXNzYWdlOiBcIlByZXNpXHUwMEYzbiBkZSBzYWxpZGEgZWxldmFkYVwiLCBjb29sZG93bl9taW51dGVzOiA1IH0sXG4gICAgICAgICAgICAgICAgeyBpZDogNiwgbmFtZTogXCJDYXVkYWwgYmFqbyBCb21iYVwiLCBzZW5zb3JfdHlwZV9pZDogMTAsIGRldmljZV9pZDogNSwgY29uZGl0aW9uOiBcIjxcIiwgdGhyZXNob2xkOiA2MCwgc2V2ZXJpdHk6IFwiZGFuZ2VyXCIsIGVuYWJsZWQ6IHRydWUsIG1lc3NhZ2U6IFwiQ2F1ZGFsIGluc3VmaWNpZW50ZSBlbiByZWNpcmN1bGFjaVx1MDBGM25cIiwgY29vbGRvd25fbWludXRlczogMTAgfSxcbiAgICAgICAgICAgICAgICB7IGlkOiA3LCBuYW1lOiBcIk5pdmVsIGFsdG8gQ2xhcmlmLlwiLCBzZW5zb3JfdHlwZV9pZDogNCwgZGV2aWNlX2lkOiA2LCBjb25kaXRpb246IFwiPlwiLCB0aHJlc2hvbGQ6IDg1LCBzZXZlcml0eTogXCJ3YXJuaW5nXCIsIGVuYWJsZWQ6IHRydWUsIG1lc3NhZ2U6IFwiTml2ZWwgZGUgbG9kb3MgYWx0byBlbiBjbGFyaWZpY2Fkb3JcIiwgY29vbGRvd25fbWludXRlczogMTUgfSxcbiAgICAgICAgICAgICAgICB7IGlkOiA4LCBuYW1lOiBcIkNvbmR1Y3RpdmlkYWQgYWx0YVwiLCBzZW5zb3JfdHlwZV9pZDogNywgZGV2aWNlX2lkOiAzLCBjb25kaXRpb246IFwiPlwiLCB0aHJlc2hvbGQ6IDMwMDAsIHNldmVyaXR5OiBcImRhbmdlclwiLCBlbmFibGVkOiB0cnVlLCBtZXNzYWdlOiBcIkNvbmR1Y3RpdmlkYWQgZnVlcmEgZGUgZXNwZWNpZmljYWNpXHUwMEYzblwiLCBjb29sZG93bl9taW51dGVzOiAxMCB9LFxuICAgICAgICAgICAgICAgIHsgaWQ6IDksIG5hbWU6IFwicEggRWZsdWVudGUgYmFqb1wiLCBzZW5zb3JfdHlwZV9pZDogNSwgZGV2aWNlX2lkOiA4LCBjb25kaXRpb246IFwiPFwiLCB0aHJlc2hvbGQ6IDYuMCwgc2V2ZXJpdHk6IFwiZGFuZ2VyXCIsIGVuYWJsZWQ6IHRydWUsIG1lc3NhZ2U6IFwicEggZGUgZWZsdWVudGUgZnVlcmEgZGUgbm9ybWFcIiwgY29vbGRvd25fbWludXRlczogNSB9LFxuICAgICAgICAgICAgICAgIHsgaWQ6IDEwLCBuYW1lOiBcIk9SUCBiYWpvIExhYm9yYXRvcmlvXCIsIHNlbnNvcl90eXBlX2lkOiA5LCBkZXZpY2VfaWQ6IDcsIGNvbmRpdGlvbjogXCI8XCIsIHRocmVzaG9sZDogMCwgc2V2ZXJpdHk6IFwid2FybmluZ1wiLCBlbmFibGVkOiB0cnVlLCBtZXNzYWdlOiBcIk9SUCBuZWdhdGl2byBlbiBsYWJvcmF0b3Jpb1wiLCBjb29sZG93bl9taW51dGVzOiAyMCB9LFxuICAgICAgICAgICAgXTtcbiAgICAgICAgICAgIGxldCBuZXh0SWQgPSAxMDA7XG4gICAgICAgICAgICBjb25zdCBhdXRob3JpemVkID0gKHJlcSkgPT5cbiAgICAgICAgICAgICAgICB0b2tlbnMuaGFzKFxuICAgICAgICAgICAgICAgICAgICAocmVxLmhlYWRlcnMuYXV0aG9yaXphdGlvbiB8fCBcIlwiKS5yZXBsYWNlKFwiQmVhcmVyIFwiLCBcIlwiKSxcbiAgICAgICAgICAgICAgICApO1xuICAgICAgICAgICAgY29uc3Qgc2VuZCA9IChyZXMsIHN0YXR1cywgZGF0YSkgPT4ge1xuICAgICAgICAgICAgICAgIHJlcy5zdGF0dXNDb2RlID0gc3RhdHVzO1xuICAgICAgICAgICAgICAgIHJlcy5zZXRIZWFkZXIoXCJDb250ZW50LVR5cGVcIiwgXCJhcHBsaWNhdGlvbi9qc29uXCIpO1xuICAgICAgICAgICAgICAgIHJlcy5lbmQoSlNPTi5zdHJpbmdpZnkoZGF0YSkpO1xuICAgICAgICAgICAgfTtcbiAgICAgICAgICAgIHNlcnZlci5taWRkbGV3YXJlcy51c2UoYXN5bmMgKHJlcSwgcmVzLCBuZXh0KSA9PiB7XG4gICAgICAgICAgICAgICAgaWYgKCFyZXEudXJsLnN0YXJ0c1dpdGgoXCIvYXBpL1wiKSkgcmV0dXJuIG5leHQoKTtcbiAgICAgICAgICAgICAgICBjb25zdCB1cmwgPSBuZXcgVVJMKHJlcS51cmwsIFwiaHR0cDovL2xvY2FsaG9zdFwiKSxcbiAgICAgICAgICAgICAgICAgICAgcCA9IHVybC5wYXRobmFtZS5zbGljZSg0KTtcbiAgICAgICAgICAgICAgICBsZXQgYm9keSA9IHt9O1xuICAgICAgICAgICAgICAgIHRyeSB7XG4gICAgICAgICAgICAgICAgICAgIGlmIChbXCJQT1NUXCIsIFwiUFVUXCIsIFwiUEFUQ0hcIl0uaW5jbHVkZXMocmVxLm1ldGhvZCkpIHtcbiAgICAgICAgICAgICAgICAgICAgICAgIGxldCByYXcgPSBcIlwiO1xuICAgICAgICAgICAgICAgICAgICAgICAgZm9yIGF3YWl0IChjb25zdCBjIG9mIHJlcSkgcmF3ICs9IGM7XG4gICAgICAgICAgICAgICAgICAgICAgICBib2R5ID0gKHJlcS5oZWFkZXJzW1wiY29udGVudC10eXBlXCJdIHx8IFwiXCIpLmluY2x1ZGVzKFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIFwiYXBwbGljYXRpb24veC13d3ctZm9ybS11cmxlbmNvZGVkXCIsXG4gICAgICAgICAgICAgICAgICAgICAgICApXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgPyBPYmplY3QuZnJvbUVudHJpZXMobmV3IFVSTFNlYXJjaFBhcmFtcyhyYXcpKVxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIDogSlNPTi5wYXJzZShyYXcgfHwgXCJ7fVwiKTtcbiAgICAgICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgICAgIH0gY2F0Y2gge1xuICAgICAgICAgICAgICAgICAgICByZXR1cm4gc2VuZChyZXMsIDQwMCwgeyBtZXNzYWdlOiBcIlNvbGljaXR1ZCBpbnZcdTAwRTFsaWRhXCIgfSk7XG4gICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgICAgIGlmIChwID09PSBcIi9hdXRoL2xvZ2luXCIpIHtcbiAgICAgICAgICAgICAgICAgICAgaWYgKFxuICAgICAgICAgICAgICAgICAgICAgICAgYm9keS5lbWFpbCAhPT0gXCJkZW1vQHNpbm9hLmxvY2FsXCIgfHxcbiAgICAgICAgICAgICAgICAgICAgICAgIGJvZHkucGFzc3dvcmQgIT09IFwibG9jYWwtcHJldmlld1wiXG4gICAgICAgICAgICAgICAgICAgIClcbiAgICAgICAgICAgICAgICAgICAgICAgIHJldHVybiBzZW5kKHJlcywgNDIyLCB7XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgbWVzc2FnZTogXCJVc2EgbGEgY3VlbnRhIGxvY2FsIGRlIGRlbW9zdHJhY2lcdTAwRjNuLlwiLFxuICAgICAgICAgICAgICAgICAgICAgICAgfSk7XG4gICAgICAgICAgICAgICAgICAgIGNvbnN0IHRva2VuID0gcmFuZG9tVVVJRCgpO1xuICAgICAgICAgICAgICAgICAgICB0b2tlbnMuYWRkKHRva2VuKTtcbiAgICAgICAgICAgICAgICAgICAgcmV0dXJuIHNlbmQocmVzLCAyMDAsIHtcbiAgICAgICAgICAgICAgICAgICAgICAgIGFjY2Vzc190b2tlbjogdG9rZW4sXG4gICAgICAgICAgICAgICAgICAgICAgICB1c2VyOiB7XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgaWQ6IDEsXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgbmFtZTogXCJBZG1pbiBEZW1vXCIsXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgZW1haWw6IFwiZGVtb0BzaW5vYS5sb2NhbFwiLFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIGlzX2FkbWluOiB0cnVlLFxuICAgICAgICAgICAgICAgICAgICAgICAgfSxcbiAgICAgICAgICAgICAgICAgICAgfSk7XG4gICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgICAgIGlmIChwID09PSBcIi9wdWJsaWMvZ3JhcGgvYm9vdHN0cmFwXCIpXG4gICAgICAgICAgICAgICAgICAgIHJldHVybiBzZW5kKHJlcywgMjAwLCB7XG4gICAgICAgICAgICAgICAgICAgICAgICBkYXRhOiB7IGRldmljZXMsIGRlZmF1bHRfc2Vuc29yX2lkOiAxIH0sXG4gICAgICAgICAgICAgICAgICAgIH0pO1xuICAgICAgICAgICAgICAgIGNvbnN0IG1hdGNoID0gcC5tYXRjaChcbiAgICAgICAgICAgICAgICAgICAgL15cXC9wdWJsaWNcXC9ncmFwaFxcL3NlbnNvcnNcXC8oXFxkKylcXC9zZXJpZXMkLyxcbiAgICAgICAgICAgICAgICApO1xuICAgICAgICAgICAgICAgIGlmIChtYXRjaCkge1xuICAgICAgICAgICAgICAgICAgICBjb25zdCBpZCA9IE51bWJlcihtYXRjaFsxXSk7XG4gICAgICAgICAgICAgICAgICAgIGlmICghYWxsLmZpbmQoKHMpID0+IHMuaWQgPT09IGlkKSlcbiAgICAgICAgICAgICAgICAgICAgICAgIHJldHVybiBzZW5kKHJlcywgNDA0LCB7IG1lc3NhZ2U6IFwiTm8gZGlzcG9uaWJsZVwiIH0pO1xuICAgICAgICAgICAgICAgICAgICBjb25zdCBmcm9tID0gRGF0ZS5wYXJzZSh1cmwuc2VhcmNoUGFyYW1zLmdldChcImZyb21cIikpLFxuICAgICAgICAgICAgICAgICAgICAgICAgdG8gPSBEYXRlLnBhcnNlKHVybC5zZWFyY2hQYXJhbXMuZ2V0KFwidG9cIikpO1xuICAgICAgICAgICAgICAgICAgICBpZiAoXG4gICAgICAgICAgICAgICAgICAgICAgICAhTnVtYmVyLmlzRmluaXRlKGZyb20pIHx8XG4gICAgICAgICAgICAgICAgICAgICAgICAhTnVtYmVyLmlzRmluaXRlKHRvKSB8fFxuICAgICAgICAgICAgICAgICAgICAgICAgZnJvbSA+PSB0byB8fFxuICAgICAgICAgICAgICAgICAgICAgICAgdG8gLSBmcm9tID4gODY0MDEwMDBcbiAgICAgICAgICAgICAgICAgICAgKVxuICAgICAgICAgICAgICAgICAgICAgICAgcmV0dXJuIHNlbmQocmVzLCA0MjIsIHsgbWVzc2FnZTogXCJWZW50YW5hIGludlx1MDBFMWxpZGFcIiB9KTtcbiAgICAgICAgICAgICAgICAgICAgY29uc3Qgc3RlcCA9IDIwMDAsXG4gICAgICAgICAgICAgICAgICAgICAgICBwb2ludHMgPSBbXTtcbiAgICAgICAgICAgICAgICAgICAgZm9yIChcbiAgICAgICAgICAgICAgICAgICAgICAgIGxldCB0ID0gTWF0aC5jZWlsKGZyb20gLyBzdGVwKSAqIHN0ZXA7XG4gICAgICAgICAgICAgICAgICAgICAgICB0IDwgdG87XG4gICAgICAgICAgICAgICAgICAgICAgICB0ICs9IHN0ZXBcbiAgICAgICAgICAgICAgICAgICAgKVxuICAgICAgICAgICAgICAgICAgICAgICAgcG9pbnRzLnB1c2goe1xuICAgICAgICAgICAgICAgICAgICAgICAgICAgIHJlYWRpbmdfaWQ6IGAke2lkfS0ke3R9YCxcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICB2YWx1ZTogdmFsdWUoaWQsIHQpLFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIHRpbWVzdGFtcDogbmV3IERhdGUodCkudG9JU09TdHJpbmcoKSxcbiAgICAgICAgICAgICAgICAgICAgICAgIH0pO1xuICAgICAgICAgICAgICAgICAgICBjb25zdCB2YWxzID0gcG9pbnRzLm1hcCgocCkgPT4gcC52YWx1ZSk7XG4gICAgICAgICAgICAgICAgICAgIHJldHVybiBzZW5kKHJlcywgMjAwLCB7XG4gICAgICAgICAgICAgICAgICAgICAgICBkYXRhOiB7XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgc2Vuc29yX2lkOiBpZCxcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICBwb2ludHMsXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgc3RhdHM6IHtcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgbWluOiBNYXRoLm1pbiguLi52YWxzKSxcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgbWF4OiBNYXRoLm1heCguLi52YWxzKSxcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgbWVhbjpcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIHZhbHMucmVkdWNlKChhLCBiKSA9PiBhICsgYiwgMCkgL1xuICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgdmFscy5sZW5ndGgsXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIGNvdW50OiB2YWxzLmxlbmd0aCxcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICB9LFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIHRydW5jYXRlZDogZmFsc2UsXG4gICAgICAgICAgICAgICAgICAgICAgICB9LFxuICAgICAgICAgICAgICAgICAgICB9KTtcbiAgICAgICAgICAgICAgICB9XG4gICAgICAgICAgICAgICAgaWYgKCFhdXRob3JpemVkKHJlcSkpXG4gICAgICAgICAgICAgICAgICAgIHJldHVybiBzZW5kKHJlcywgNDAxLCB7XG4gICAgICAgICAgICAgICAgICAgICAgICBtZXNzYWdlOiBcIkluaWNpYSBzZXNpXHUwMEYzbiBwYXJhIGFjY2VkZXIuXCIsXG4gICAgICAgICAgICAgICAgICAgIH0pO1xuICAgICAgICAgICAgICAgIGlmIChwID09PSBcIi9hdXRoL21lXCIpXG4gICAgICAgICAgICAgICAgICAgIHJldHVybiBzZW5kKHJlcywgMjAwLCB7XG4gICAgICAgICAgICAgICAgICAgICAgICBkYXRhOiB7XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgaWQ6IDEsXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgbmFtZTogXCJBZG1pbiBEZW1vXCIsXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgZW1haWw6IFwiZGVtb0BzaW5vYS5sb2NhbFwiLFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIGlzX2FkbWluOiB0cnVlLFxuICAgICAgICAgICAgICAgICAgICAgICAgfSxcbiAgICAgICAgICAgICAgICAgICAgfSk7XG4gICAgICAgICAgICAgICAgaWYgKHAgPT09IFwiL2F1dGgvbG9nb3V0XCIpIHtcbiAgICAgICAgICAgICAgICAgICAgdG9rZW5zLmRlbGV0ZShcbiAgICAgICAgICAgICAgICAgICAgICAgIChyZXEuaGVhZGVycy5hdXRob3JpemF0aW9uIHx8IFwiXCIpLnJlcGxhY2UoXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgXCJCZWFyZXIgXCIsXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgXCJcIixcbiAgICAgICAgICAgICAgICAgICAgICAgICksXG4gICAgICAgICAgICAgICAgICAgICk7XG4gICAgICAgICAgICAgICAgICAgIHNvY2tldEF1dGguY2xlYXIoKTtcbiAgICAgICAgICAgICAgICAgICAgcmV0dXJuIHNlbmQocmVzLCAyMDAsIHt9KTtcbiAgICAgICAgICAgICAgICB9XG4gICAgICAgICAgICAgICAgaWYgKHAgPT09IFwiL2Jyb2FkY2FzdGluZy9hdXRoXCIpIHtcbiAgICAgICAgICAgICAgICAgICAgaWYgKFxuICAgICAgICAgICAgICAgICAgICAgICAgIS9eKHByaXZhdGUtc2Vuc29yXFwuWzEtNF18cHJpdmF0ZS1hbGVydHN8cHJpdmF0ZS1kZXZpY2Utc3RhdHVzKSQvLnRlc3QoXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgYm9keS5jaGFubmVsX25hbWUgfHwgXCJcIixcbiAgICAgICAgICAgICAgICAgICAgICAgIClcbiAgICAgICAgICAgICAgICAgICAgKVxuICAgICAgICAgICAgICAgICAgICAgICAgcmV0dXJuIHNlbmQocmVzLCA0MDMsIHt9KTtcbiAgICAgICAgICAgICAgICAgICAgc29ja2V0QXV0aC5zZXQoXG4gICAgICAgICAgICAgICAgICAgICAgICBgJHtib2R5LnNvY2tldF9pZH06JHtib2R5LmNoYW5uZWxfbmFtZX1gLFxuICAgICAgICAgICAgICAgICAgICAgICAgdHJ1ZSxcbiAgICAgICAgICAgICAgICAgICAgKTtcbiAgICAgICAgICAgICAgICAgICAgcmV0dXJuIHNlbmQocmVzLCAyMDAsIHsgYXV0aDogXCJsb2NhbDpwcmV2aWV3XCIgfSk7XG4gICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgICAgIGlmIChwID09PSBcIi9kYXNoYm9hcmQvcHJlZmVyZW5jZXNcIikge1xuICAgICAgICAgICAgICAgICAgICBpZiAocmVxLm1ldGhvZCA9PT0gXCJQVVRcIikge1xuICAgICAgICAgICAgICAgICAgICAgICAgbGF5b3V0ID0gYm9keS5sYXlvdXQ7XG4gICAgICAgICAgICAgICAgICAgICAgICB3cml0ZUZpbGVTeW5jKFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIFwiLmRlbW8tZGF0YS93b3Jrc3BhY2UuanNvblwiLFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIEpTT04uc3RyaW5naWZ5KGxheW91dCksXG4gICAgICAgICAgICAgICAgICAgICAgICApO1xuICAgICAgICAgICAgICAgICAgICB9XG4gICAgICAgICAgICAgICAgICAgIHJldHVybiBzZW5kKHJlcywgMjAwLCB7IGxheW91dCB9KTtcbiAgICAgICAgICAgICAgICB9XG4gICAgICAgICAgICAgICAgaWYgKHAgPT09IFwiL2NvbmZpZy9ydW50aW1lXCIpXG4gICAgICAgICAgICAgICAgICAgIHJldHVybiBzZW5kKHJlcywgMjAwLCB7XG4gICAgICAgICAgICAgICAgICAgICAgICBkYXRhOiB7IGFsZXJ0X3NvdW5kX2VuYWJsZWQ6IGZhbHNlIH0sXG4gICAgICAgICAgICAgICAgICAgIH0pO1xuICAgICAgICAgICAgICAgIGlmIChwID09PSBcIi9hbGVydHMvYWN0aXZlXCIpIHJldHVybiBzZW5kKHJlcywgMjAwLCB7YWxlcnRzLCBjb3VudDogYWxlcnRzLmxlbmd0aH0pO1xuICAgICAgICAgICAgaWYgKHAuc3RhcnRzV2l0aChcIi9hbGVydHNcIikpIHtcbiAgICAgICAgICAgICAgICAgICAgY29uc3QgaWQgPSBwLm1hdGNoKC9eXFwvYWxlcnRzXFwvKFxcZCspJC8pPy5bMV07XG4gICAgICAgICAgICAgICAgICAgIHJldHVybiBzZW5kKFxuICAgICAgICAgICAgICAgICAgICAgICAgcmVzLFxuICAgICAgICAgICAgICAgICAgICAgICAgMjAwLFxuICAgICAgICAgICAgICAgICAgICAgICAgaWRcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICA/IHsgZGF0YTogYWxlcnRzLmZpbmQoKGEpID0+IGEuaWQgPT09IE51bWJlcihpZCkpIH1cbiAgICAgICAgICAgICAgICAgICAgICAgICAgICA6IHsgZGF0YTogYWxlcnRzLCBjb3VudDogMiB9LFxuICAgICAgICAgICAgICAgICAgICApO1xuICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgICAgICBpZiAocCA9PT0gXCIvZGV2aWNlc1wiKVxuICAgICAgICAgICAgICAgICAgICByZXR1cm4gc2VuZChyZXMsIDIwMCwge1xuICAgICAgICAgICAgICAgICAgICAgICAgZGF0YTogZGV2aWNlcy5tYXAoKGQpID0+ICh7IC4uLmQsIHN0YXR1czogXCJvbmxpbmVcIiB9KSksXG4gICAgICAgICAgICAgICAgICAgIH0pO1xuICAgICAgICAgICAgICAgIGlmIChwID09PSBcIi9kYXNoYm9hcmQvbWV0cmljc1wiKVxuICAgICAgICAgICAgICAgICAgICByZXR1cm4gc2VuZChyZXMsIDIwMCwgeyBkYXRhOiB7IGxhdGVzdF9yZWFkaW5nczogW10gfSB9KTtcbiAgICAgICAgICAgICAgICBpZiAocC5tYXRjaCgvXlxcL3NlbnNvcnNcXC9cXGQrXFwvbGF0ZXN0LXJlYWRpbmdzJC8pKVxuICAgICAgICAgICAgICAgICAgICByZXR1cm4gc2VuZChyZXMsIDIwMCwgeyBkYXRhOiBbXSB9KTtcbiAgICAgICAgICAgICAgICBpZiAocCA9PT0gXCIvc2Vuc29yc1wiKSByZXR1cm4gc2VuZChyZXMsIDIwMCwgeyBkYXRhOiBhbGwgfSk7XG5cbiAgICAgICAgICAgICAgICAvLyAtLS0gQXV0aCBleHRyYXMgLS0tXG4gICAgICAgICAgICAgICAgaWYgKHAgPT09IFwiL2F1dGgvcmVnaXN0ZXJcIikge1xuICAgICAgICAgICAgICAgICAgICBjb25zdCB0b2tlbiA9IHJhbmRvbVVVSUQoKTtcbiAgICAgICAgICAgICAgICAgICAgdG9rZW5zLmFkZCh0b2tlbik7XG4gICAgICAgICAgICAgICAgICAgIHJldHVybiBzZW5kKHJlcywgMjAwLCB7XG4gICAgICAgICAgICAgICAgICAgICAgICBhY2Nlc3NfdG9rZW46IHRva2VuLFxuICAgICAgICAgICAgICAgICAgICAgICAgdXNlcjogeyBpZDogOTksIG5hbWU6IGJvZHkubmFtZSB8fCBcIk51ZXZvXCIsIGVtYWlsOiBib2R5LmVtYWlsLCBpc19hZG1pbjogZmFsc2UgfSxcbiAgICAgICAgICAgICAgICAgICAgfSk7XG4gICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgICAgIGlmIChwID09PSBcIi9hdXRoL2ZvcmdvdC1wYXNzd29yZFwiKSByZXR1cm4gc2VuZChyZXMsIDIwMCwgeyBtZXNzYWdlOiBcIkVtYWlsIGVudmlhZG8uXCIgfSk7XG4gICAgICAgICAgICAgICAgaWYgKHAgPT09IFwiL2F1dGgvcmVzZXQtcGFzc3dvcmRcIikgcmV0dXJuIHNlbmQocmVzLCAyMDAsIHsgbWVzc2FnZTogXCJDb250cmFzZVx1MDBGMWEgcmVzdGFibGVjaWRhLlwiIH0pO1xuXG4gICAgICAgICAgICAgICAgLy8gLS0tIFNlbnNvciBkZXRhaWwgKyBDUlVEIC0tLVxuICAgICAgICAgICAgICAgIGNvbnN0IHNlbnNvck1hdGNoID0gcC5tYXRjaCgvXlxcL3NlbnNvcnNcXC8oXFxkKykoPzpcXC8oLiopKT8kLyk7XG4gICAgICAgICAgICAgICAgaWYgKHNlbnNvck1hdGNoKSB7XG4gICAgICAgICAgICAgICAgICAgIGNvbnN0IHNpZCA9IE51bWJlcihzZW5zb3JNYXRjaFsxXSk7XG4gICAgICAgICAgICAgICAgICAgIGNvbnN0IHN1YiA9IHNlbnNvck1hdGNoWzJdIHx8IFwiXCI7XG4gICAgICAgICAgICAgICAgICAgIGNvbnN0IHNlbnNvciA9IGFsbC5maW5kKChzKSA9PiBzLmlkID09PSBzaWQpO1xuICAgICAgICAgICAgICAgICAgICBpZiAoIXNlbnNvcikgcmV0dXJuIHNlbmQocmVzLCA0MDQsIHsgbWVzc2FnZTogXCJTZW5zb3Igbm8gZW5jb250cmFkb1wiIH0pO1xuICAgICAgICAgICAgICAgICAgICBpZiAocmVxLm1ldGhvZCA9PT0gXCJERUxFVEVcIikgcmV0dXJuIHNlbmQocmVzLCAyMDAsIHsgbWVzc2FnZTogXCJFbGltaW5hZG9cIiB9KTtcbiAgICAgICAgICAgICAgICAgICAgaWYgKHJlcS5tZXRob2QgPT09IFwiUFVUXCIpIHJldHVybiBzZW5kKHJlcywgMjAwLCB7IGRhdGE6IHsgLi4uc2Vuc29yLCAuLi5ib2R5IH0gfSk7XG4gICAgICAgICAgICAgICAgICAgIGlmIChzdWIgPT09IFwicmVhZGluZ3NcIiB8fCBzdWIgPT09IFwicmVhZGluZ3MvZXhwb3J0XCIpIHtcbiAgICAgICAgICAgICAgICAgICAgICAgIGNvbnN0IGZyb20gPSBEYXRlLnBhcnNlKHVybC5zZWFyY2hQYXJhbXMuZ2V0KFwiZnJvbVwiKSB8fCBEYXRlLm5vdygpIC0gODY0MDAwMDApO1xuICAgICAgICAgICAgICAgICAgICAgICAgY29uc3QgdG8gPSBEYXRlLnBhcnNlKHVybC5zZWFyY2hQYXJhbXMuZ2V0KFwidG9cIikgfHwgRGF0ZS5ub3coKSk7XG4gICAgICAgICAgICAgICAgICAgICAgICBjb25zdCBwb2ludHMgPSBbXTtcbiAgICAgICAgICAgICAgICAgICAgICAgIGZvciAobGV0IHQgPSBNYXRoLmNlaWwoZnJvbSAvIDIwMDApICogMjAwMDsgdCA8IHRvOyB0ICs9IDIwMDApXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgcG9pbnRzLnB1c2goeyByZWFkaW5nX2lkOiBgJHtzaWR9LSR7dH1gLCB2YWx1ZTogdmFsdWUoc2lkLCB0KSwgdGltZXN0YW1wOiBuZXcgRGF0ZSh0KS50b0lTT1N0cmluZygpIH0pO1xuICAgICAgICAgICAgICAgICAgICAgICAgcmV0dXJuIHNlbmQocmVzLCAyMDAsIHsgZGF0YTogcG9pbnRzIH0pO1xuICAgICAgICAgICAgICAgICAgICB9XG4gICAgICAgICAgICAgICAgICAgIGlmIChzdWIgPT09IFwibGF0ZXN0LXJlYWRpbmdzXCIpIHtcbiAgICAgICAgICAgICAgICAgICAgICAgIGNvbnN0IG5vdyA9IERhdGUubm93KCk7XG4gICAgICAgICAgICAgICAgICAgICAgICBjb25zdCBwdHMgPSBbXTtcbiAgICAgICAgICAgICAgICAgICAgICAgIGZvciAobGV0IGkgPSA1OyBpID49IDA7IGktLSkge1xuICAgICAgICAgICAgICAgICAgICAgICAgICAgIGNvbnN0IHQgPSBub3cgLSBpICogNjAwMDA7XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgcHRzLnB1c2goeyByZWFkaW5nX2lkOiBgJHtzaWR9LSR7dH1gLCB2YWx1ZTogdmFsdWUoc2lkLCB0KSwgcmVhZGluZ190aW1lOiBuZXcgRGF0ZSh0KS50b0lTT1N0cmluZygpIH0pO1xuICAgICAgICAgICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgICAgICAgICAgICAgcmV0dXJuIHNlbmQocmVzLCAyMDAsIHsgZGF0YTogcHRzIH0pO1xuICAgICAgICAgICAgICAgICAgICB9XG4gICAgICAgICAgICAgICAgICAgIGlmIChyZXEubWV0aG9kID09PSBcIlBPU1RcIikgcmV0dXJuIHNlbmQocmVzLCAyMDAsIHsgZGF0YTogc2Vuc29yIH0pO1xuICAgICAgICAgICAgICAgICAgICByZXR1cm4gc2VuZChyZXMsIDIwMCwgeyBkYXRhOiBzZW5zb3IgfSk7XG4gICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgICAgIGlmIChwID09PSBcIi9zZW5zb3JzXCIgJiYgcmVxLm1ldGhvZCA9PT0gXCJQT1NUXCIpIHtcbiAgICAgICAgICAgICAgICAgICAgcmV0dXJuIHNlbmQocmVzLCAyMDAsIHsgZGF0YTogeyBpZDogKytuZXh0SWQsIC4uLmJvZHksIHN0YXR1czogdHJ1ZSB9IH0pO1xuICAgICAgICAgICAgICAgIH1cblxuICAgICAgICAgICAgICAgIC8vIC0tLSBEZXZpY2UgZGV0YWlsICsgQ1JVRCAtLS1cbiAgICAgICAgICAgICAgICBjb25zdCBkZXZpY2VNYXRjaCA9IHAubWF0Y2goL15cXC9kZXZpY2VzXFwvKFxcZCspKD86XFwvKC4qKSk/JC8pO1xuICAgICAgICAgICAgICAgIGlmIChkZXZpY2VNYXRjaCkge1xuICAgICAgICAgICAgICAgICAgICBjb25zdCBkaWQgPSBOdW1iZXIoZGV2aWNlTWF0Y2hbMV0pO1xuICAgICAgICAgICAgICAgICAgICBjb25zdCBzdWIgPSBkZXZpY2VNYXRjaFsyXSB8fCBcIlwiO1xuICAgICAgICAgICAgICAgICAgICBjb25zdCBkZXZpY2UgPSBkZXZpY2VzLmZpbmQoKGQpID0+IGQuaWQgPT09IGRpZCk7XG4gICAgICAgICAgICAgICAgICAgIGlmICghZGV2aWNlKSByZXR1cm4gc2VuZChyZXMsIDQwNCwgeyBtZXNzYWdlOiBcIkRpc3Bvc2l0aXZvIG5vIGVuY29udHJhZG9cIiB9KTtcbiAgICAgICAgICAgICAgICAgICAgaWYgKHJlcS5tZXRob2QgPT09IFwiREVMRVRFXCIpIHJldHVybiBzZW5kKHJlcywgMjAwLCB7IG1lc3NhZ2U6IFwiRWxpbWluYWRvXCIgfSk7XG4gICAgICAgICAgICAgICAgICAgIGlmIChyZXEubWV0aG9kID09PSBcIlBVVFwiKSByZXR1cm4gc2VuZChyZXMsIDIwMCwgeyBkYXRhOiB7IC4uLmRldmljZSwgLi4uYm9keSwgc3RhdHVzOiBcIm9ubGluZVwiIH0gfSk7XG4gICAgICAgICAgICAgICAgICAgIGlmIChzdWIgPT09IFwic2Vuc29yLWxpc3RcIikge1xuICAgICAgICAgICAgICAgICAgICAgICAgcmV0dXJuIHNlbmQocmVzLCAyMDAsIHsgZGF0YTogZGV2aWNlLnNlbnNvcnMubWFwKChzKSA9PiAoeyAuLi5zLCBkZXZpY2VfaWQ6IGRpZCB9KSkgfSk7XG4gICAgICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgICAgICAgICAgaWYgKHN1YiA9PT0gXCJzdGF0dXNcIiAmJiByZXEubWV0aG9kID09PSBcIlBPU1RcIikge1xuICAgICAgICAgICAgICAgICAgICAgICAgcmV0dXJuIHNlbmQocmVzLCAyMDAsIHsgZGF0YTogeyBpZDogZGlkLCBzdGF0dXM6IGJvZHkuc3RhdHVzID8gXCJvbmxpbmVcIiA6IFwib2ZmbGluZVwiLCBpc19hY3RpdmU6IGJvZHkuc3RhdHVzIH0gfSk7XG4gICAgICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgICAgICAgICAgaWYgKHJlcS5tZXRob2QgPT09IFwiUE9TVFwiKSByZXR1cm4gc2VuZChyZXMsIDIwMCwgeyBkYXRhOiBkZXZpY2UgfSk7XG4gICAgICAgICAgICAgICAgICAgIHJldHVybiBzZW5kKHJlcywgMjAwLCB7IGRhdGE6IHsgLi4uZGV2aWNlLCBzdGF0dXM6IFwib25saW5lXCIsIGxhYjogbGFic1swXSwgZGV2aWNlX3R5cGU6IGRldmljZVR5cGVzWzBdIH0gfSk7XG4gICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgICAgIGlmIChwID09PSBcIi9kZXZpY2VzXCIgJiYgcmVxLm1ldGhvZCA9PT0gXCJQT1NUXCIpIHtcbiAgICAgICAgICAgICAgICAgICAgcmV0dXJuIHNlbmQocmVzLCAyMDAsIHsgZGF0YTogeyBpZDogKytuZXh0SWQsIC4uLmJvZHksIHN0YXR1czogXCJvbmxpbmVcIiB9IH0pO1xuICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgICAgICBpZiAocCA9PT0gXCIvZGV2aWNlcy9zdGF0dXMtc25hcHNob3RcIikge1xuICAgICAgICAgICAgICAgICAgICByZXR1cm4gc2VuZChyZXMsIDIwMCwgeyBkYXRhOiBkZXZpY2VzLm1hcCgoZCkgPT4gKHsgZGV2aWNlX2lkOiBkLmlkLCBzdGF0dXM6IGQuc3RhdHVzLCBpc19hY3RpdmU6IGQuc3RhdHVzID09PSBcIm9ubGluZVwiLCBjaGFuZ2VkX2F0OiBkLmxhc3Rfc2VlbiwgaXBfYWRkcmVzczogZC5pcF9hZGRyZXNzLCBmaXJtd2FyZV92ZXJzaW9uOiBkLmZpcm13YXJlX3ZlcnNpb24gfSkpIH0pO1xuICAgICAgICAgICAgICAgIH1cblxuICAgICAgICAgICAgICAgIC8vIC0tLSBBbGVydCBkZXRhaWwgKyByZXNvbHZlIC0tLVxuICAgICAgICAgICAgICAgIGNvbnN0IGFsZXJ0TWF0Y2ggPSBwLm1hdGNoKC9eXFwvYWxlcnRzXFwvKFxcZCspKD86XFwvKC4qKSk/JC8pO1xuICAgICAgICAgICAgICAgIGlmIChhbGVydE1hdGNoKSB7XG4gICAgICAgICAgICAgICAgICAgIGNvbnN0IGFpZCA9IE51bWJlcihhbGVydE1hdGNoWzFdKTtcbiAgICAgICAgICAgICAgICAgICAgY29uc3Qgc3ViID0gYWxlcnRNYXRjaFsyXSB8fCBcIlwiO1xuICAgICAgICAgICAgICAgICAgICBjb25zdCBhbGVydCA9IGFsZXJ0cy5maW5kKChhKSA9PiBhLmlkID09PSBhaWQpO1xuICAgICAgICAgICAgICAgICAgICBpZiAoIWFsZXJ0KSByZXR1cm4gc2VuZChyZXMsIDQwNCwgeyBtZXNzYWdlOiBcIkFsZXJ0YSBubyBlbmNvbnRyYWRhXCIgfSk7XG4gICAgICAgICAgICAgICAgICAgIGlmIChzdWIgPT09IFwicmVzb2x2ZVwiICYmIHJlcS5tZXRob2QgPT09IFwiUEFUQ0hcIikge1xuICAgICAgICAgICAgICAgICAgICAgICAgYWxlcnQucmVzb2x2ZWQgPSB0cnVlO1xuICAgICAgICAgICAgICAgICAgICAgICAgYWxlcnQucmVzb2x2ZWRfYXQgPSBuZXcgRGF0ZSgpLnRvSVNPU3RyaW5nKCk7XG4gICAgICAgICAgICAgICAgICAgICAgICByZXR1cm4gc2VuZChyZXMsIDIwMCwgeyBkYXRhOiBhbGVydCB9KTtcbiAgICAgICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgICAgICAgICByZXR1cm4gc2VuZChyZXMsIDIwMCwgeyBkYXRhOiBhbGVydCB9KTtcbiAgICAgICAgICAgICAgICB9XG4gICAgICAgICAgICAgICAgaWYgKHAgPT09IFwiL2FsZXJ0cy91bnJlc29sdmVkXCIpIHtcbiAgICAgICAgICAgICAgICAgICAgcmV0dXJuIHNlbmQocmVzLCAyMDAsIHsgZGF0YTogYWxlcnRzLmZpbHRlcigoYSkgPT4gIWEucmVzb2x2ZWQpLCBjb3VudDogYWxlcnRzLmZpbHRlcigoYSkgPT4gIWEucmVzb2x2ZWQpLmxlbmd0aCB9KTtcbiAgICAgICAgICAgICAgICB9XG4gICAgICAgICAgICAgICAgaWYgKHAgPT09IFwiL2FsZXJ0cy9yZXNvbHZlLWFsbFwiICYmIHJlcS5tZXRob2QgPT09IFwiUE9TVFwiKSB7XG4gICAgICAgICAgICAgICAgICAgIGFsZXJ0cy5mb3JFYWNoKChhKSA9PiB7IGEucmVzb2x2ZWQgPSB0cnVlOyBhLnJlc29sdmVkX2F0ID0gbmV3IERhdGUoKS50b0lTT1N0cmluZygpOyB9KTtcbiAgICAgICAgICAgICAgICAgICAgcmV0dXJuIHNlbmQocmVzLCAyMDAsIHsgbWVzc2FnZTogXCJUb2RhcyByZXN1ZWx0YXNcIiB9KTtcbiAgICAgICAgICAgICAgICB9XG5cbiAgICAgICAgICAgICAgICAvLyAtLS0gQWxlcnQgcnVsZXMgLS0tXG4gICAgICAgICAgICAgICAgaWYgKHAgPT09IFwiL2FsZXJ0LXJ1bGVzL2NyZWF0ZVwiKSB7XG4gICAgICAgICAgICAgICAgICAgIHJldHVybiBzZW5kKHJlcywgMjAwLCB7IGRhdGE6IHsgc2Vuc29yX3R5cGVzOiBzZW5zb3JUeXBlcywgZGV2aWNlczogZGV2aWNlcy5tYXAoKGQpID0+ICh7IGlkOiBkLmlkLCBuYW1lOiBkLm5hbWUgfSkpLCBzZW5zb3JzOiBhbGwubWFwKChzKSA9PiAoeyBpZDogcy5pZCwgbmFtZTogcy5uYW1lLCBkZXZpY2VfaWQ6IHMuZGV2aWNlLmlkIH0pKSB9IH0pO1xuICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgICAgICBjb25zdCBhbGVydFJ1bGVNYXRjaCA9IHAubWF0Y2goL15cXC9hbGVydC1ydWxlc1xcLyhcXGQrKSQvKTtcbiAgICAgICAgICAgICAgICBpZiAoYWxlcnRSdWxlTWF0Y2gpIHtcbiAgICAgICAgICAgICAgICAgICAgY29uc3QgcmlkID0gTnVtYmVyKGFsZXJ0UnVsZU1hdGNoWzFdKTtcbiAgICAgICAgICAgICAgICAgICAgY29uc3QgcnVsZSA9IGFsZXJ0UnVsZXMuZmluZCgocikgPT4gci5pZCA9PT0gcmlkKTtcbiAgICAgICAgICAgICAgICAgICAgaWYgKCFydWxlKSByZXR1cm4gc2VuZChyZXMsIDQwNCwgeyBtZXNzYWdlOiBcIlJlZ2xhIG5vIGVuY29udHJhZGFcIiB9KTtcbiAgICAgICAgICAgICAgICAgICAgaWYgKHJlcS5tZXRob2QgPT09IFwiREVMRVRFXCIpIHJldHVybiBzZW5kKHJlcywgMjAwLCB7IG1lc3NhZ2U6IFwiRWxpbWluYWRhXCIgfSk7XG4gICAgICAgICAgICAgICAgICAgIGlmIChyZXEubWV0aG9kID09PSBcIlBVVFwiKSByZXR1cm4gc2VuZChyZXMsIDIwMCwgeyBkYXRhOiB7IC4uLnJ1bGUsIC4uLmJvZHkgfSB9KTtcbiAgICAgICAgICAgICAgICAgICAgcmV0dXJuIHNlbmQocmVzLCAyMDAsIHsgZGF0YTogcnVsZSB9KTtcbiAgICAgICAgICAgICAgICB9XG4gICAgICAgICAgICAgICAgaWYgKHAgPT09IFwiL2FsZXJ0LXJ1bGVzXCIpIHtcbiAgICAgICAgICAgICAgICAgICAgaWYgKHJlcS5tZXRob2QgPT09IFwiUE9TVFwiKSByZXR1cm4gc2VuZChyZXMsIDIwMCwgeyBkYXRhOiB7IGlkOiArK25leHRJZCwgLi4uYm9keSwgZW5hYmxlZDogdHJ1ZSB9IH0pO1xuICAgICAgICAgICAgICAgICAgICByZXR1cm4gc2VuZChyZXMsIDIwMCwgeyBkYXRhOiBhbGVydFJ1bGVzLCBjb3VudDogYWxlcnRSdWxlcy5sZW5ndGggfSk7XG4gICAgICAgICAgICAgICAgfVxuXG4gICAgICAgICAgICAgICAgLy8gLS0tIENvbmZpZyBhZG1pbiAtLS1cbiAgICAgICAgICAgICAgICBpZiAocCA9PT0gXCIvY29uZmlnL2FsZXJ0c1wiKSB7XG4gICAgICAgICAgICAgICAgICAgIGlmIChyZXEubWV0aG9kID09PSBcIlBVVFwiKSByZXR1cm4gc2VuZChyZXMsIDIwMCwgeyBtZXNzYWdlOiBcIkFjdHVhbGl6YWRvXCIgfSk7XG4gICAgICAgICAgICAgICAgICAgIHJldHVybiBzZW5kKHJlcywgMjAwLCB7IGRhdGE6IHsgYWxlcnRfc291bmRfZW5hYmxlZDogZmFsc2UsIGFsZXJ0X2VtYWlsX2VuYWJsZWQ6IHRydWUsIGFsZXJ0X2Nvb2xkb3duX21pbnV0ZXM6IDUgfSB9KTtcbiAgICAgICAgICAgICAgICB9XG4gICAgICAgICAgICAgICAgaWYgKHAgPT09IFwiL2NvbmZpZy9lbWFpbFwiKSB7XG4gICAgICAgICAgICAgICAgICAgIGlmIChyZXEubWV0aG9kID09PSBcIlBVVFwiKSByZXR1cm4gc2VuZChyZXMsIDIwMCwgeyBtZXNzYWdlOiBcIkFjdHVhbGl6YWRvXCIgfSk7XG4gICAgICAgICAgICAgICAgICAgIHJldHVybiBzZW5kKHJlcywgMjAwLCB7IGRhdGE6IHsgbWFpbF9ob3N0OiBcInNtdHAuZXhhbXBsZS5jb21cIiwgbWFpbF9wb3J0OiA1ODcsIG1haWxfdXNlcm5hbWU6IFwiXCIsIG1haWxfZW5jcnlwdGlvbjogXCJ0bHNcIiwgbWFpbF9mcm9tX2FkZHJlc3M6IFwiYWxlcnRzQHNpbm9hLmxvY2FsXCIsIGNvbmZpZ3VyZWQ6IGZhbHNlIH0gfSk7XG4gICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgICAgIGlmIChwID09PSBcIi9jb25maWcvZW1haWwvdGVzdFwiICYmIHJlcS5tZXRob2QgPT09IFwiUE9TVFwiKSB7XG4gICAgICAgICAgICAgICAgICAgIHJldHVybiBzZW5kKHJlcywgMjAwLCB7IG1lc3NhZ2U6IFwiRW1haWwgZGUgcHJ1ZWJhIGVudmlhZG8uXCIgfSk7XG4gICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgICAgIGlmIChwID09PSBcIi9jb25maWcvc3lzdGVtLWluZm9cIikge1xuICAgICAgICAgICAgICAgICAgICByZXR1cm4gc2VuZChyZXMsIDIwMCwgeyBkYXRhOiB7IHBocF92ZXJzaW9uOiBcIjguMlwiLCBsYXJhdmVsX3ZlcnNpb246IFwiMTEuMFwiLCBhcHBfdmVyc2lvbjogXCIyLjAuMFwiLCBlbnZpcm9ubWVudDogXCJkZW1vXCIsIHVwdGltZTogXCIyZCA1aFwiLCBkYl9zaXplOiBcIjEyLjQgTUJcIiwgcmVkaXNfY29ubmVjdGVkOiB0cnVlIH0gfSk7XG4gICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgICAgIGlmIChwID09PSBcIi9jb25maWcvZ2VuZXJhbFwiICYmIHJlcS5tZXRob2QgPT09IFwiUFVUXCIpIHtcbiAgICAgICAgICAgICAgICAgICAgcmV0dXJuIHNlbmQocmVzLCAyMDAsIHsgbWVzc2FnZTogXCJBY3R1YWxpemFkb1wiIH0pO1xuICAgICAgICAgICAgICAgIH1cblxuICAgICAgICAgICAgICAgIC8vIC0tLSBDYXRhbG9nczogbGFicyAtLS1cbiAgICAgICAgICAgICAgICBjb25zdCBsYWJNYXRjaCA9IHAubWF0Y2goL15cXC9sYWJzXFwvKFxcZCspJC8pO1xuICAgICAgICAgICAgICAgIGlmIChsYWJNYXRjaCkge1xuICAgICAgICAgICAgICAgICAgICBjb25zdCBsaWQgPSBOdW1iZXIobGFiTWF0Y2hbMV0pO1xuICAgICAgICAgICAgICAgICAgICBjb25zdCBsYWIgPSBsYWJzLmZpbmQoKGwpID0+IGwuaWQgPT09IGxpZCk7XG4gICAgICAgICAgICAgICAgICAgIGlmICghbGFiKSByZXR1cm4gc2VuZChyZXMsIDQwNCwgeyBtZXNzYWdlOiBcIkxhYiBubyBlbmNvbnRyYWRvXCIgfSk7XG4gICAgICAgICAgICAgICAgICAgIGlmIChyZXEubWV0aG9kID09PSBcIkRFTEVURVwiKSByZXR1cm4gc2VuZChyZXMsIDIwMCwgeyBtZXNzYWdlOiBcIkVsaW1pbmFkb1wiIH0pO1xuICAgICAgICAgICAgICAgICAgICBpZiAocmVxLm1ldGhvZCA9PT0gXCJQVVRcIikgcmV0dXJuIHNlbmQocmVzLCAyMDAsIHsgZGF0YTogeyAuLi5sYWIsIC4uLmJvZHkgfSB9KTtcbiAgICAgICAgICAgICAgICAgICAgcmV0dXJuIHNlbmQocmVzLCAyMDAsIHsgZGF0YTogbGFiIH0pO1xuICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgICAgICBpZiAocCA9PT0gXCIvbGFic1wiKSB7XG4gICAgICAgICAgICAgICAgICAgIGlmIChyZXEubWV0aG9kID09PSBcIlBPU1RcIikgcmV0dXJuIHNlbmQocmVzLCAyMDAsIHsgZGF0YTogeyBpZDogKytuZXh0SWQsIC4uLmJvZHkgfSB9KTtcbiAgICAgICAgICAgICAgICAgICAgcmV0dXJuIHNlbmQocmVzLCAyMDAsIHsgZGF0YTogbGFicywgY291bnQ6IGxhYnMubGVuZ3RoIH0pO1xuICAgICAgICAgICAgICAgIH1cblxuICAgICAgICAgICAgICAgIC8vIC0tLSBDYXRhbG9nczogc2Vuc29yLXR5cGVzIC0tLVxuICAgICAgICAgICAgICAgIGNvbnN0IHN0TWF0Y2ggPSBwLm1hdGNoKC9eXFwvc2Vuc29yLXR5cGVzXFwvKFxcZCspJC8pO1xuICAgICAgICAgICAgICAgIGlmIChzdE1hdGNoKSB7XG4gICAgICAgICAgICAgICAgICAgIGNvbnN0IHN0aWQgPSBOdW1iZXIoc3RNYXRjaFsxXSk7XG4gICAgICAgICAgICAgICAgICAgIGNvbnN0IHN0ID0gc2Vuc29yVHlwZXMuZmluZCgocykgPT4gcy5pZCA9PT0gc3RpZCk7XG4gICAgICAgICAgICAgICAgICAgIGlmICghc3QpIHJldHVybiBzZW5kKHJlcywgNDA0LCB7IG1lc3NhZ2U6IFwiVGlwbyBubyBlbmNvbnRyYWRvXCIgfSk7XG4gICAgICAgICAgICAgICAgICAgIGlmIChyZXEubWV0aG9kID09PSBcIkRFTEVURVwiKSByZXR1cm4gc2VuZChyZXMsIDIwMCwgeyBtZXNzYWdlOiBcIkVsaW1pbmFkb1wiIH0pO1xuICAgICAgICAgICAgICAgICAgICBpZiAocmVxLm1ldGhvZCA9PT0gXCJQVVRcIikgcmV0dXJuIHNlbmQocmVzLCAyMDAsIHsgZGF0YTogeyAuLi5zdCwgLi4uYm9keSB9IH0pO1xuICAgICAgICAgICAgICAgICAgICByZXR1cm4gc2VuZChyZXMsIDIwMCwgeyBkYXRhOiBzdCB9KTtcbiAgICAgICAgICAgICAgICB9XG4gICAgICAgICAgICAgICAgaWYgKHAgPT09IFwiL3NlbnNvci10eXBlc1wiKSB7XG4gICAgICAgICAgICAgICAgICAgIGlmIChyZXEubWV0aG9kID09PSBcIlBPU1RcIikgcmV0dXJuIHNlbmQocmVzLCAyMDAsIHsgZGF0YTogeyBpZDogKytuZXh0SWQsIC4uLmJvZHkgfSB9KTtcbiAgICAgICAgICAgICAgICAgICAgcmV0dXJuIHNlbmQocmVzLCAyMDAsIHsgZGF0YTogc2Vuc29yVHlwZXMsIGNvdW50OiBzZW5zb3JUeXBlcy5sZW5ndGggfSk7XG4gICAgICAgICAgICAgICAgfVxuXG4gICAgICAgICAgICAgICAgLy8gLS0tIENhdGFsb2dzOiBkZXZpY2UtdHlwZXMgLS0tXG4gICAgICAgICAgICAgICAgY29uc3QgZHRNYXRjaCA9IHAubWF0Y2goL15cXC9kZXZpY2UtdHlwZXNcXC8oXFxkKykkLyk7XG4gICAgICAgICAgICAgICAgaWYgKGR0TWF0Y2gpIHtcbiAgICAgICAgICAgICAgICAgICAgY29uc3QgZHRpZCA9IE51bWJlcihkdE1hdGNoWzFdKTtcbiAgICAgICAgICAgICAgICAgICAgY29uc3QgZHQgPSBkZXZpY2VUeXBlcy5maW5kKChkKSA9PiBkLmlkID09PSBkdGlkKTtcbiAgICAgICAgICAgICAgICAgICAgaWYgKCFkdCkgcmV0dXJuIHNlbmQocmVzLCA0MDQsIHsgbWVzc2FnZTogXCJUaXBvIG5vIGVuY29udHJhZG9cIiB9KTtcbiAgICAgICAgICAgICAgICAgICAgaWYgKHJlcS5tZXRob2QgPT09IFwiREVMRVRFXCIpIHJldHVybiBzZW5kKHJlcywgMjAwLCB7IG1lc3NhZ2U6IFwiRWxpbWluYWRvXCIgfSk7XG4gICAgICAgICAgICAgICAgICAgIGlmIChyZXEubWV0aG9kID09PSBcIlBVVFwiKSByZXR1cm4gc2VuZChyZXMsIDIwMCwgeyBkYXRhOiB7IC4uLmR0LCAuLi5ib2R5IH0gfSk7XG4gICAgICAgICAgICAgICAgICAgIHJldHVybiBzZW5kKHJlcywgMjAwLCB7IGRhdGE6IGR0IH0pO1xuICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgICAgICBpZiAocCA9PT0gXCIvZGV2aWNlLXR5cGVzXCIpIHtcbiAgICAgICAgICAgICAgICAgICAgaWYgKHJlcS5tZXRob2QgPT09IFwiUE9TVFwiKSByZXR1cm4gc2VuZChyZXMsIDIwMCwgeyBkYXRhOiB7IGlkOiArK25leHRJZCwgLi4uYm9keSB9IH0pO1xuICAgICAgICAgICAgICAgICAgICByZXR1cm4gc2VuZChyZXMsIDIwMCwgeyBkYXRhOiBkZXZpY2VUeXBlcywgY291bnQ6IGRldmljZVR5cGVzLmxlbmd0aCB9KTtcbiAgICAgICAgICAgICAgICB9XG5cbiAgICAgICAgICAgICAgICAvLyAtLS0gVXNlcnMgLS0tXG4gICAgICAgICAgICAgICAgY29uc3QgdXNlclJvbGVNYXRjaCA9IHAubWF0Y2goL15cXC91c2Vyc1xcLyhcXGQrKVxcL3JvbGUkLyk7XG4gICAgICAgICAgICAgICAgaWYgKHVzZXJSb2xlTWF0Y2gpIHtcbiAgICAgICAgICAgICAgICAgICAgY29uc3QgdWlkID0gTnVtYmVyKHVzZXJSb2xlTWF0Y2hbMV0pO1xuICAgICAgICAgICAgICAgICAgICBjb25zdCB1c2VyID0gZGVtb1VzZXJzLmZpbmQoKHUpID0+IHUuaWQgPT09IHVpZCk7XG4gICAgICAgICAgICAgICAgICAgIGlmICghdXNlcikgcmV0dXJuIHNlbmQocmVzLCA0MDQsIHsgbWVzc2FnZTogXCJVc3VhcmlvIG5vIGVuY29udHJhZG9cIiB9KTtcbiAgICAgICAgICAgICAgICAgICAgdXNlci5pc19hZG1pbiA9IGJvZHkuaXNfYWRtaW47XG4gICAgICAgICAgICAgICAgICAgIHJldHVybiBzZW5kKHJlcywgMjAwLCB7IGRhdGE6IHVzZXIgfSk7XG4gICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgICAgIGlmIChwID09PSBcIi91c2Vyc1wiKSB7XG4gICAgICAgICAgICAgICAgICAgIHJldHVybiBzZW5kKHJlcywgMjAwLCB7IGRhdGE6IGRlbW9Vc2VycywgY291bnQ6IGRlbW9Vc2Vycy5sZW5ndGggfSk7XG4gICAgICAgICAgICAgICAgfVxuXG4gICAgICAgICAgICAgICAgLy8gLS0tIFByb2ZpbGUgLS0tXG4gICAgICAgICAgICAgICAgaWYgKHAgPT09IFwiL3Byb2ZpbGVcIikge1xuICAgICAgICAgICAgICAgICAgICByZXR1cm4gc2VuZChyZXMsIDIwMCwgeyBkYXRhOiB7IGlkOiAxLCBuYW1lOiBcIkFkbWluIERlbW9cIiwgZW1haWw6IFwiZGVtb0BzaW5vYS5sb2NhbFwiLCBpc19hZG1pbjogdHJ1ZSwgY3JlYXRlZF9hdDogXCIyMDI1LTAxLTE1VDEwOjAwOjAwWlwiIH0gfSk7XG4gICAgICAgICAgICAgICAgfVxuXG4gICAgICAgICAgICAgICAgLy8gLS0tIE1ldHJpY3MgLS0tXG4gICAgICAgICAgICAgICAgaWYgKHAgPT09IFwiL21ldHJpY3NcIikge1xuICAgICAgICAgICAgICAgICAgICByZXR1cm4gc2VuZChyZXMsIDIwMCwgeyBkYXRhOiB7IHRvdGFsX3NlbnNvcnM6IGFsbC5sZW5ndGgsIHRvdGFsX2RldmljZXM6IGRldmljZXMubGVuZ3RoLCBhY3RpdmVfYWxlcnRzOiBhbGVydHMuZmlsdGVyKChhKSA9PiAhYS5yZXNvbHZlZCkubGVuZ3RoLCB0b3RhbF9sYWJzOiBsYWJzLmxlbmd0aCwgcmVhZGluZ3NfdG9kYXk6IDQyODcsIHVwdGltZV9wZXJjZW50OiA5OC4zLCBvbmxpbmVfZGV2aWNlczogZGV2aWNlcy5maWx0ZXIoKGQpID0+IGQuc3RhdHVzID09PSBcIm9ubGluZVwiKS5sZW5ndGgsIG9mZmxpbmVfZGV2aWNlczogZGV2aWNlcy5maWx0ZXIoKGQpID0+IGQuc3RhdHVzID09PSBcIm9mZmxpbmVcIikubGVuZ3RoLCB0b3RhbF9hbGVydF9ydWxlczogYWxlcnRSdWxlcy5sZW5ndGgsIGVuYWJsZWRfcnVsZXM6IGFsZXJ0UnVsZXMuZmlsdGVyKChyKSA9PiByLmVuYWJsZWQpLmxlbmd0aCB9IH0pO1xuICAgICAgICAgICAgICAgIH1cblxuICAgICAgICAgICAgICAgIHJldHVybiBzZW5kKHJlcywgNDA0LCB7XG4gICAgICAgICAgICAgICAgICAgIG1lc3NhZ2U6XG4gICAgICAgICAgICAgICAgICAgICAgICBcIkVzdGEgdmlzdGEgbm8gZm9ybWEgcGFydGUgZGUgbGEgZGVtb3N0cmFjaVx1MDBGM24gbG9jYWwuXCIsXG4gICAgICAgICAgICAgICAgfSk7XG4gICAgICAgICAgICB9KTtcbiAgICAgICAgICAgIGNvbnN0IHdzcyA9IG5ldyBXZWJTb2NrZXRTZXJ2ZXIoeyBub1NlcnZlcjogdHJ1ZSB9KTtcbiAgICAgICAgICAgIGNvbnN0IG9uVXBncmFkZSA9IChyZXEsIHNvY2tldCwgaGVhZCkgPT4ge1xuICAgICAgICAgICAgICAgIGlmIChyZXEudXJsLnN0YXJ0c1dpdGgoXCIvYXBwL1wiKSlcbiAgICAgICAgICAgICAgICAgICAgd3NzLmhhbmRsZVVwZ3JhZGUocmVxLCBzb2NrZXQsIGhlYWQsICh3cykgPT5cbiAgICAgICAgICAgICAgICAgICAgICAgIHdzcy5lbWl0KFwiY29ubmVjdGlvblwiLCB3cyksXG4gICAgICAgICAgICAgICAgICAgICk7XG4gICAgICAgICAgICB9O1xuICAgICAgICAgICAgc2VydmVyLmh0dHBTZXJ2ZXIub24oXCJ1cGdyYWRlXCIsIG9uVXBncmFkZSk7XG4gICAgICAgICAgICB3c3Mub24oXCJjb25uZWN0aW9uXCIsICh3cykgPT4ge1xuICAgICAgICAgICAgICAgIHdzLmNoYW5uZWxzID0gbmV3IFNldCgpO1xuICAgICAgICAgICAgICAgIHdzLnNpZCA9IGAke0RhdGUubm93KCl9LiR7TWF0aC5mbG9vcihNYXRoLnJhbmRvbSgpICogMWU1KX1gO1xuICAgICAgICAgICAgICAgIHdzLnNlbmQoXG4gICAgICAgICAgICAgICAgICAgIEpTT04uc3RyaW5naWZ5KHtcbiAgICAgICAgICAgICAgICAgICAgICAgIGV2ZW50OiBcInB1c2hlcjpjb25uZWN0aW9uX2VzdGFibGlzaGVkXCIsXG4gICAgICAgICAgICAgICAgICAgICAgICBkYXRhOiBKU09OLnN0cmluZ2lmeSh7XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgc29ja2V0X2lkOiB3cy5zaWQsXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgYWN0aXZpdHlfdGltZW91dDogMTIwLFxuICAgICAgICAgICAgICAgICAgICAgICAgfSksXG4gICAgICAgICAgICAgICAgICAgIH0pLFxuICAgICAgICAgICAgICAgICk7XG4gICAgICAgICAgICAgICAgd3Mub24oXCJtZXNzYWdlXCIsIChyYXcpID0+IHtcbiAgICAgICAgICAgICAgICAgICAgdHJ5IHtcbiAgICAgICAgICAgICAgICAgICAgICAgIGNvbnN0IG0gPSBKU09OLnBhcnNlKHJhdyk7XG4gICAgICAgICAgICAgICAgICAgICAgICBpZiAobS5ldmVudCA9PT0gXCJwdXNoZXI6cGluZ1wiKVxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIHdzLnNlbmQoXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIEpTT04uc3RyaW5naWZ5KHtcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIGV2ZW50OiBcInB1c2hlcjpwb25nXCIsXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBkYXRhOiBcInt9XCIsXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIH0pLFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgICk7XG4gICAgICAgICAgICAgICAgICAgICAgICBpZiAobS5ldmVudCA9PT0gXCJwdXNoZXI6c3Vic2NyaWJlXCIpIHtcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICBjb25zdCBjaCA9IG0uZGF0YS5jaGFubmVsO1xuICAgICAgICAgICAgICAgICAgICAgICAgICAgIGlmIChcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgY2guc3RhcnRzV2l0aChcInByaXZhdGUtXCIpICYmXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICFzb2NrZXRBdXRoLmhhcyhgJHt3cy5zaWR9OiR7Y2h9YClcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICApXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIHJldHVybjtcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICB3cy5jaGFubmVscy5hZGQoY2gpO1xuICAgICAgICAgICAgICAgICAgICAgICAgICAgIHdzLnNlbmQoXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIEpTT04uc3RyaW5naWZ5KHtcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIGV2ZW50OiBcInB1c2hlcl9pbnRlcm5hbDpzdWJzY3JpcHRpb25fc3VjY2VlZGVkXCIsXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBjaGFubmVsOiBjaCxcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIGRhdGE6IFwie31cIixcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgfSksXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgKTtcbiAgICAgICAgICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgICAgICAgICAgICAgIGlmIChtLmV2ZW50ID09PSBcInB1c2hlcjp1bnN1YnNjcmliZVwiKVxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIHdzLmNoYW5uZWxzLmRlbGV0ZShtLmRhdGEuY2hhbm5lbCk7XG4gICAgICAgICAgICAgICAgICAgIH0gY2F0Y2gge31cbiAgICAgICAgICAgICAgICB9KTtcbiAgICAgICAgICAgIH0pO1xuICAgICAgICAgICAgY29uc3QgdGltZXIgPSBzZXRJbnRlcnZhbCgoKSA9PiB7XG4gICAgICAgICAgICAgICAgY29uc3QgdCA9IE1hdGguZmxvb3IoRGF0ZS5ub3coKSAvIDIwMDApICogMjAwMDtcbiAgICAgICAgICAgICAgICBmb3IgKGNvbnN0IHdzIG9mIHdzcy5jbGllbnRzKSB7XG4gICAgICAgICAgICAgICAgICAgIGZvciAoY29uc3QgY2ggb2Ygd3MuY2hhbm5lbHMpIHtcbiAgICAgICAgICAgICAgICAgICAgICAgIGlmIChcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICBjaC5zdGFydHNXaXRoKFwicHJpdmF0ZS1cIikgJiZcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAhc29ja2V0QXV0aC5oYXMoYCR7d3Muc2lkfToke2NofWApXG4gICAgICAgICAgICAgICAgICAgICAgICApXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgY29udGludWU7XG4gICAgICAgICAgICAgICAgICAgICAgICBjb25zdCBpZCA9IE51bWJlcihcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICBjaC5tYXRjaCgvXig/OnByaXZhdGUtKT9zZW5zb3JcXC4oXFxkKykkLyk/LlsxXSxcbiAgICAgICAgICAgICAgICAgICAgICAgICk7XG4gICAgICAgICAgICAgICAgICAgICAgICBpZiAoIWFsbC5maW5kKChzKSA9PiBzLmlkID09PSBpZCkpIGNvbnRpbnVlO1xuICAgICAgICAgICAgICAgICAgICAgICAgd3Muc2VuZChcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICBKU09OLnN0cmluZ2lmeSh7XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIGV2ZW50OiBcIkFwcFxcXFxFdmVudHNcXFxcTmV3U2Vuc29yUmVhZGluZ1wiLFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBjaGFubmVsOiBjaCxcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgZGF0YTogSlNPTi5zdHJpbmdpZnkoe1xuICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgcmVhZGluZ19pZDogYCR7aWR9LSR7dH1gLFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgc2Vuc29yX2lkOiBpZCxcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIHZhbHVlOiB2YWx1ZShpZCwgdCksXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICByZWFkaW5nX3RpbWU6IG5ldyBEYXRlKHQpLnRvSVNPU3RyaW5nKCksXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIH0pLFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIH0pLFxuICAgICAgICAgICAgICAgICAgICAgICAgKTtcbiAgICAgICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgIH0sIDIwMDApO1xuICAgICAgICAgICAgc2VydmVyLmh0dHBTZXJ2ZXIub25jZShcImNsb3NlXCIsICgpID0+IHtcbiAgICAgICAgICAgICAgICBjbGVhckludGVydmFsKHRpbWVyKTtcbiAgICAgICAgICAgICAgICB3c3MuY2xvc2UoKTtcbiAgICAgICAgICAgICAgICBzZXJ2ZXIuaHR0cFNlcnZlci5vZmYoXCJ1cGdyYWRlXCIsIG9uVXBncmFkZSk7XG4gICAgICAgICAgICB9KTtcbiAgICAgICAgfSxcbiAgICB9O1xufVxuIiwgImNvbnN0IF9fdml0ZV9pbmplY3RlZF9vcmlnaW5hbF9kaXJuYW1lID0gXCJDOlxcXFxVc2Vyc1xcXFxqdnJpbmNvblxcXFxEb2N1bWVudHNcXFxcaW90X3BsYXRmb3JtXFxcXGlvdC1wbGF0Zm9ybS12MlxcXFxmcm9udFwiO2NvbnN0IF9fdml0ZV9pbmplY3RlZF9vcmlnaW5hbF9maWxlbmFtZSA9IFwiQzpcXFxcVXNlcnNcXFxcanZyaW5jb25cXFxcRG9jdW1lbnRzXFxcXGlvdF9wbGF0Zm9ybVxcXFxpb3QtcGxhdGZvcm0tdjJcXFxcZnJvbnRcXFxcdml0ZS5jb25maWcuanNcIjtjb25zdCBfX3ZpdGVfaW5qZWN0ZWRfb3JpZ2luYWxfaW1wb3J0X21ldGFfdXJsID0gXCJmaWxlOi8vL0M6L1VzZXJzL2p2cmluY29uL0RvY3VtZW50cy9pb3RfcGxhdGZvcm0vaW90LXBsYXRmb3JtLXYyL2Zyb250L3ZpdGUuY29uZmlnLmpzXCI7aW1wb3J0IHsgbGFiRGVtb1BsdWdpbiB9IGZyb20gXCIuL3NjcmlwdHMvZGVtby1zZXJ2ZXIubWpzXCI7XG5pbXBvcnQgeyBmaWxlVVJMVG9QYXRoLCBVUkwgfSBmcm9tIFwibm9kZTp1cmxcIjtcblxuaW1wb3J0IHZ1ZSBmcm9tIFwiQHZpdGVqcy9wbHVnaW4tdnVlXCI7XG5pbXBvcnQgeyBkZWZpbmVDb25maWcsIGxvYWRFbnYgfSBmcm9tIFwidml0ZVwiO1xuXG5leHBvcnQgZGVmYXVsdCBkZWZpbmVDb25maWcoKHsgbW9kZSB9KSA9PiB7XG4gICAgY29uc3QgZW52ID0gbG9hZEVudihtb2RlLCBwcm9jZXNzLmN3ZCgpLCBcIlwiKTtcblxuICAgIHJldHVybiB7XG4gICAgICAgIC8vIE5vcm1hbCBkZXZlbG9wbWVudCBhbmQgdGhlIGRlbW8gbWF5IHJ1biB0b2dldGhlci4gVGhlaXIgZGlmZmVyZW50XG4gICAgICAgIC8vIGRlZmluZXMgbXVzdCBub3Qgb3ZlcndyaXRlIGVhY2ggb3RoZXIncyBvcHRpbWl6ZWQgZGVwZW5kZW5jaWVzLlxuICAgICAgICBjYWNoZURpcjogYG5vZGVfbW9kdWxlcy8udml0ZS0ke21vZGV9YCxcbiAgICAgICAgZGVmaW5lOlxuICAgICAgICAgICAgbW9kZSA9PT0gXCJkZW1vXCJcbiAgICAgICAgICAgICAgICA/IHtcbiAgICAgICAgICAgICAgICAgICAgICBcImltcG9ydC5tZXRhLmVudi5WSVRFX1BVU0hFUl9BUFBfS0VZXCI6XG4gICAgICAgICAgICAgICAgICAgICAgICAgIEpTT04uc3RyaW5naWZ5KFwibG9jYWwtcHJldmlld1wiKSxcbiAgICAgICAgICAgICAgICAgICAgICBcImltcG9ydC5tZXRhLmVudi5WSVRFX1BVU0hFUl9IT1NUXCI6XG4gICAgICAgICAgICAgICAgICAgICAgICAgIEpTT04uc3RyaW5naWZ5KFwiMTI3LjAuMC4xXCIpLFxuICAgICAgICAgICAgICAgICAgICAgIFwiaW1wb3J0Lm1ldGEuZW52LlZJVEVfUFVTSEVSX1BPUlRcIjpcbiAgICAgICAgICAgICAgICAgICAgICAgICAgSlNPTi5zdHJpbmdpZnkoXCI1MTczXCIpLFxuICAgICAgICAgICAgICAgICAgICAgIFwiaW1wb3J0Lm1ldGEuZW52LlZJVEVfUFVTSEVSX0ZPUkNFX1RMU1wiOlxuICAgICAgICAgICAgICAgICAgICAgICAgICBKU09OLnN0cmluZ2lmeShcImZhbHNlXCIpLFxuICAgICAgICAgICAgICAgICAgICAgIFwiaW1wb3J0Lm1ldGEuZW52LlZJVEVfUFVTSEVSX1NDSEVNRVwiOlxuICAgICAgICAgICAgICAgICAgICAgICAgICBKU09OLnN0cmluZ2lmeShcImh0dHBcIiksXG4gICAgICAgICAgICAgICAgICAgICAgXCJpbXBvcnQubWV0YS5lbnYuVklURV9BUElfQkFTRV9VUkxcIjpcbiAgICAgICAgICAgICAgICAgICAgICAgICAgSlNPTi5zdHJpbmdpZnkoXCIvYXBpXCIpLFxuICAgICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgICAgIDoge30sXG4gICAgICAgIHBsdWdpbnM6IFt2dWUoKSwgLi4uKG1vZGUgPT09IFwiZGVtb1wiID8gW2xhYkRlbW9QbHVnaW4oKV0gOiBbXSldLFxuICAgICAgICByZXNvbHZlOiB7XG4gICAgICAgICAgICBhbGlhczoge1xuICAgICAgICAgICAgICAgIFwiQFwiOiBmaWxlVVJMVG9QYXRoKG5ldyBVUkwoXCIuL3NyY1wiLCBpbXBvcnQubWV0YS51cmwpKSxcbiAgICAgICAgICAgIH0sXG4gICAgICAgIH0sXG4gICAgICAgIHNlcnZlcjoge1xuICAgICAgICAgICAgaG9zdDogXCIxMjcuMC4wLjFcIixcbiAgICAgICAgICAgIHBvcnQ6IDUxNzMsXG4gICAgICAgICAgICBzdHJpY3RQb3J0OiB0cnVlLFxuICAgICAgICAgICAgcHJveHk6IHtcbiAgICAgICAgICAgICAgICBcIi9hcGlcIjoge1xuICAgICAgICAgICAgICAgICAgICB0YXJnZXQ6XG4gICAgICAgICAgICAgICAgICAgICAgICBlbnYuVklURV9BUElfUFJPWFlfVEFSR0VUIHx8IFwiaHR0cDovL2xvY2FsaG9zdDo4MDAwXCIsXG4gICAgICAgICAgICAgICAgICAgIGNoYW5nZU9yaWdpbjogdHJ1ZSxcbiAgICAgICAgICAgICAg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QUNULE1BQU0sS0FBSyxVQUFVO0FBQUEsa0JBQ2pCLFlBQVksR0FBRyxFQUFFLElBQUksQ0FBQztBQUFBLGtCQUN0QixXQUFXO0FBQUEsa0JBQ1gsT0FBTyxNQUFNLElBQUksQ0FBQztBQUFBLGtCQUNsQixjQUFjLElBQUksS0FBSyxDQUFDLEVBQUUsWUFBWTtBQUFBLGdCQUMxQyxDQUFDO0FBQUEsY0FDTCxDQUFDO0FBQUEsWUFDTDtBQUFBLFVBQ0o7QUFBQSxRQUNKO0FBQUEsTUFDSixHQUFHLEdBQUk7QUFDUCxhQUFPLFdBQVcsS0FBSyxTQUFTLE1BQU07QUFDbEMsc0JBQWMsS0FBSztBQUNuQixZQUFJLE1BQU07QUFDVixlQUFPLFdBQVcsSUFBSSxXQUFXLFNBQVM7QUFBQSxNQUM5QyxDQUFDO0FBQUEsSUFDTDtBQUFBLEVBQ0o7QUFDSjs7O0FDdHlCQSxTQUFTLGVBQWUsT0FBQUMsWUFBVztBQUVuQyxPQUFPLFNBQVM7QUFDaEIsU0FBUyxjQUFjLGVBQWU7QUFKMk0sSUFBTSwyQ0FBMkM7QUFNbFMsSUFBTyxzQkFBUSxhQUFhLENBQUMsRUFBRSxLQUFLLE1BQU07QUFDdEMsUUFBTSxNQUFNLFFBQVEsTUFBTSxRQUFRLElBQUksR0FBRyxFQUFFO0FBRTNDLFNBQU87QUFBQTtBQUFBO0FBQUEsSUFHSCxVQUFVLHNCQUFzQixJQUFJO0FBQUEsSUFDcEMsUUFDSSxTQUFTLFNBQ0g7QUFBQSxNQUNJLHVDQUNJLEtBQUssVUFBVSxlQUFlO0FBQUEsTUFDbEMsb0NBQ0ksS0FBSyxVQUFVLFdBQVc7QUFBQSxNQUM5QixvQ0FDSSxLQUFLLFVBQVUsTUFBTTtBQUFBLE1BQ3pCLHlDQUNJLEtBQUssVUFBVSxPQUFPO0FBQUEsTUFDMUIsc0NBQ0ksS0FBSyxVQUFVLE1BQU07QUFBQSxNQUN6QixxQ0FDSSxLQUFLLFVBQVUsTUFBTTtBQUFBLElBQzdCLElBQ0EsQ0FBQztBQUFBLElBQ1gsU0FBUyxDQUFDLElBQUksR0FBRyxHQUFJLFNBQVMsU0FBUyxDQUFDLGNBQWMsQ0FBQyxJQUFJLENBQUMsQ0FBRTtBQUFBLElBQzlELFNBQVM7QUFBQSxNQUNMLE9BQU87QUFBQSxRQUNILEtBQUssY0FBYyxJQUFJQyxLQUFJLFNBQVMsd0NBQWUsQ0FBQztBQUFBLE1BQ3hEO0FBQUEsSUFDSjtBQUFBLElBQ0EsUUFBUTtBQUFBLE1BQ0osTUFBTTtBQUFBLE1BQ04sTUFBTTtBQUFBLE1BQ04sWUFBWTtBQUFBLE1BQ1osT0FBTztBQUFBLFFBQ0gsUUFBUTtBQUFBLFVBQ0osUUFDSSxJQUFJLHlCQUF5QjtBQUFBLFVBQ2pDLGNBQWM7QUFBQSxRQUNsQjtBQUFBLE1BQ0o7QUFBQSxJQUNKO0FBQUEsRUFDSjtBQUNKLENBQUM7IiwKICAibmFtZXMiOiBbInAiLCAiVVJMIiwgIlVSTCJdCn0K
