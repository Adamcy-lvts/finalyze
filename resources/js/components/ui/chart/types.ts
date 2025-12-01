import type { Component } from "vue"

export type ChartConfigEntry = {
  label?: string
  icon?: Component
  color?: string
  theme?: {
    light?: string
    dark?: string
  }
}

export type ChartConfig = Record<string, ChartConfigEntry>
