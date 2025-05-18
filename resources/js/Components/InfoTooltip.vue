<template>
  <div class="info-tooltip-container" @mouseenter="showTooltip" @mouseleave="hideTooltip">
    <span class="info-icon"><i :class="icon"></i></span>
    <span class="info-label">{{ label }}</span>
    <div class="tooltip-content" v-if="show">
      <div class="tooltip-icon"><i :class="icon"></i></div>
      <div class="tooltip-text">{{ tooltipText }}</div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'InfoTooltip',
  props: {
    icon: {
      type: String,
      required: true,
    },
    label: {
      type: String,
      required: true,
    },
    tooltipText: {
      type: String,
      required: true,
    },
  },
  data() {
    return {
      show: false,
    };
  },
  methods: {
    showTooltip() {
      this.show = true;
    },
    hideTooltip() {
      this.show = false;
    },
  },
};
</script>

<style scoped>
.info-tooltip-container {
  position: relative;
  display: inline-block;
}

.info-icon {
  padding-right: 5px;
  cursor: pointer;
}

.tooltip-content {
  position: absolute;
  top: 100%;
  left: 0;
  background-color: #f0f0f0;
  border: 1px solid #ccc;
  padding: 5px;
  border-radius: 5px;
  z-index: 1000; /* Ensure it's above other content */
  display: none;
  visibility: hidden; /* Initially hidden */
}

.tooltip-content.tooltip-show {
  display: block;
  visibility: visible;
}

.tooltip-icon {
  padding-right: 5px;
}

.tooltip-text {
  font-size: 0.9em;
}
</style>