import { defineComponent, h } from 'vue';

// A deliberately small test-only replacement for vue-chartjs' Line component.
// Keeping the incoming view-model visible in attributes lets adapter tests catch
// a missing chart or a chart that is mounted without the expected props.
export const LineChartStub = defineComponent({
  name: 'LineChartStub',
  props: {
    data: {
      type: Object,
      required: true
    },
    options: {
      type: Object,
      required: true
    }
  },
  setup(props) {
    return () => {
      const dataset = props.data.datasets?.[0] || {};
      return h('div', {
        class: 'chart-stub',
        'data-chart-stub': 'line',
        'data-chart-labels': JSON.stringify(props.data.labels || []),
        'data-chart-series': JSON.stringify(dataset.data || []),
        'data-chart-unit': dataset.label || ''
      });
    };
  }
});
