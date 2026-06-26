<template>
  <form @submit.prevent="submit">
    <div class="row g-3">
      <div class="col-12 col-lg-6">
        <BaseInput v-model="form.name" label="Nombre" name="name" :error="fieldError('name')" />
      </div>

      <div class="col-12 col-lg-6">
        <label class="form-label" for="severity">Severidad</label>
        <select id="severity" v-model="form.severity" class="form-select" :class="{ 'is-invalid': fieldError('severity') }" required>
          <option value="info">Info</option>
          <option value="warning">Advertencia</option>
          <option value="danger">Critica</option>
        </select>
        <div v-if="fieldError('severity')" class="invalid-feedback">{{ fieldError('severity') }}</div>
      </div>

      <div class="col-12">
        <BaseInput v-model="form.message" label="Mensaje" name="message" :error="fieldError('message')" required />
      </div>

      <div class="col-12 col-lg-4">
        <label class="form-label" for="sensor_type_id">Tipo de sensor</label>
        <select id="sensor_type_id" v-model="form.sensor_type_id" class="form-select" :class="{ 'is-invalid': fieldError('sensor_type_id') }" required>
          <option value="">Seleccionar</option>
          <option v-for="type in metadata.sensor_types" :key="type.id" :value="type.id">
            {{ type.name }}{{ type.unit ? ` (${type.unit})` : '' }}
          </option>
        </select>
        <div v-if="fieldError('sensor_type_id')" class="invalid-feedback">{{ fieldError('sensor_type_id') }}</div>
      </div>

      <div class="col-12 col-lg-4">
        <label class="form-label" for="device_id">Dispositivo</label>
        <select id="device_id" v-model="form.device_id" class="form-select" :class="{ 'is-invalid': fieldError('device_id') }">
          <option value="">Todos</option>
          <option v-for="device in metadata.devices" :key="device.id" :value="device.id">{{ device.name }}</option>
        </select>
        <div v-if="fieldError('device_id')" class="invalid-feedback">{{ fieldError('device_id') }}</div>
      </div>

      <div class="col-12 col-lg-4">
        <label class="form-label" for="sensor_id">Sensor</label>
        <select id="sensor_id" v-model="form.sensor_id" class="form-select" :class="{ 'is-invalid': fieldError('sensor_id') }">
          <option value="">Todos</option>
          <option v-for="sensor in filteredSensors" :key="sensor.id" :value="sensor.id">
            {{ sensor.name }}
          </option>
        </select>
        <div v-if="fieldError('sensor_id')" class="invalid-feedback">{{ fieldError('sensor_id') }}</div>
      </div>

      <div class="col-12 col-lg-6">
        <BaseInput v-model="form.min_value" label="Valor minimo" name="min_value" type="number" :error="fieldError('min_value')" />
      </div>

      <div class="col-12 col-lg-6">
        <BaseInput v-model="form.max_value" label="Valor maximo" name="max_value" type="number" :error="fieldError('max_value')" />
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
      <button class="btn btn-outline-secondary" type="button" :disabled="loading" @click="$emit('cancel')">Cancelar</button>
      <BaseButton type="submit" :loading="loading">Guardar</BaseButton>
    </div>
  </form>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';

import BaseButton from '@/components/base/BaseButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import { validationMessage } from '@/utils/formatters';

const props = defineProps({
  rule: {
    type: Object,
    default: null
  },
  metadata: {
    type: Object,
    default: () => ({ sensor_types: [], devices: [], sensors: [] })
  },
  loading: {
    type: Boolean,
    default: false
  },
  errors: {
    type: Object,
    default: () => ({})
  }
});

const emit = defineEmits(['submit', 'cancel']);

const form = reactive(defaultForm());

const filteredSensors = computed(() => {
  return (props.metadata.sensors || []).filter((sensor) => {
    const matchesType = !form.sensor_type_id || Number(sensor.sensor_type_id) === Number(form.sensor_type_id);
    const matchesDevice = !form.device_id || Number(sensor.device_id) === Number(form.device_id);
    return matchesType && matchesDevice;
  });
});

watch(
  () => props.rule,
  () => Object.assign(form, defaultForm(props.rule)),
  { immediate: true }
);

watch(
  () => form.sensor_id,
  (sensorId) => {
    const sensor = (props.metadata.sensors || []).find((item) => Number(item.id) === Number(sensorId));
    if (sensor) {
      form.sensor_type_id = sensor.sensor_type_id;
      form.device_id = sensor.device_id;
    }
  }
);

function defaultForm(rule = null) {
  return {
    name: rule?.name || '',
    message: rule?.message || '',
    severity: rule?.severity || 'warning',
    sensor_type_id: rule?.sensor_type?.id || '',
    device_id: rule?.device?.id || '',
    sensor_id: rule?.sensor?.id || '',
    min_value: rule?.min_value ?? '',
    max_value: rule?.max_value ?? ''
  };
}

function nullableNumber(value) {
  return value === '' || value === null || value === undefined ? null : Number(value);
}

function submit() {
  emit('submit', {
    ...form,
    sensor_type_id: Number(form.sensor_type_id),
    device_id: form.device_id ? Number(form.device_id) : null,
    sensor_id: form.sensor_id ? Number(form.sensor_id) : null,
    min_value: nullableNumber(form.min_value),
    max_value: nullableNumber(form.max_value)
  });
}

function fieldError(field) {
  return validationMessage(props.errors, field);
}
</script>
