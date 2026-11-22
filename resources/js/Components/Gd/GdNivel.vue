<script setup>
/** Selector persistente Colegio / Universidad (mejora 6). */
import { nivel, NIVELES } from '@/lib/educacion';

defineProps({ compacto: { type: Boolean, default: false } });
</script>

<template>
    <div class="edu-nivel" :class="{ compacto }" role="group" aria-label="Nivel de las explicaciones">
        <span v-if="!compacto" class="edu-nivel-lbl">Nivel:</span>
        <button v-for="n in NIVELES" :key="n.v" type="button" :class="{ on: nivel === n.v }" :aria-pressed="nivel === n.v"
                :title="n.v === 'colegio' ? 'Explicaciones simples, cifras redondeadas y más pistas' : 'Explicaciones técnicas: HHI, FOB/CIF, NANDINA de 10 dígitos'"
                @click="nivel = n.v">{{ n.t }}</button>
    </div>
</template>
