<template>
    <div class="container-fluid py-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h4 class="fw-bold mb-1 text-dark">Students</h4>
                        <p class="text-muted small mb-0">All registered students</p>
                    </div>
                    <button class="btn btn-success fw-bold px-4" :disabled="loading || filteredRows.length === 0"
                        @click="downloadPdf">
                        <i class="bi bi-download me-2"></i>Download PDF
                    </button>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body bg-light border-bottom p-3">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold mb-1">Search</label>
                        <input v-model.trim="filters.search" type="text" class="form-control form-control-sm"
                            placeholder="Student #, name, program...">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1">Program</label>
                        <select v-model="filters.programName" class="form-select form-select-sm">
                            <option value="">All Programs</option>
                            <option v-for="program in programOptions" :key="program" :value="program">{{ program }}
                            </option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1">Sort Order</label>
                        <select v-model="filters.sortOrder" class="form-select form-select-sm">
                            <option value="asc">Ascending</option>
                            <option value="desc">Descending</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">No.</th>
                                <th>Student #</th>
                                <th>Full Name</th>
                                <th>Program</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading">
                                <td colspan="4" class="text-center py-4 text-muted">Loading students...</td>
                            </tr>
                            <tr v-else-if="filteredRows.length === 0">
                                <td colspan="4" class="text-center py-4 text-muted">No students found.</td>
                            </tr>
                            <tr v-else v-for="(row, index) in filteredRows" :key="row.id">
                                <td class="ps-3">{{ index + 1 }}</td>
                                <td class="fw-semibold">{{ row.student_number || '-' }}</td>
                                <td>{{ row.full_name || '-' }}</td>
                                <td>{{ row.program_name || 'N/A' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

const loading = ref(false);
const rows = ref([]);

const filters = ref({
    search: '',
    programName: '',
    sortOrder: 'asc',
});

const programOptions = computed(() => {
    return [...new Set(rows.value.map((row) => row.program_name).filter(Boolean))]
        .sort((a, b) => String(a).localeCompare(String(b)));
});

const filteredRows = computed(() => {
    let result = [...rows.value];

    if (filters.value.search) {
        const q = filters.value.search.toLowerCase();
        result = result.filter((row) => {
            const text = [
                row.student_number,
                row.full_name,
                row.username,
                row.email,
                row.program_name,
            ].map((item) => String(item || '').toLowerCase()).join(' ');

            return text.includes(q);
        });
    }

    if (filters.value.programName) {
        result = result.filter((row) => row.program_name === filters.value.programName);
    }

    const factor = filters.value.sortOrder === 'asc' ? 1 : -1;
    result.sort((a, b) => String(a.full_name || '').localeCompare(String(b.full_name || '')) * factor);

    return result;
});

const loadStudents = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/admin/students');
        rows.value = Array.isArray(data?.data) ? data.data : [];
    } catch (error) {
        rows.value = [];
        window.Swal?.fire({
            icon: 'error',
            title: 'Failed to load students',
            text: error?.response?.data?.message || 'Please refresh and try again.',
        });
    } finally {
        loading.value = false;
    }
};

const escapePdfText = (value) => {
    return String(value ?? '')
        .replaceAll('\\', '\\\\')
        .replaceAll('(', '\\(')
        .replaceAll(')', '\\)');
};

const formatPdfCell = (value, maxLength) => {
    const text = String(value ?? '').replace(/\s+/g, ' ').trim();
    if (text.length <= maxLength) {
        return text;
    }

    return `${text.slice(0, Math.max(0, maxLength - 3))}...`;
};

const buildPdfBytes = (lines) => {
    const objects = [];
    const addObject = (content) => {
        objects.push(content);
        return objects.length;
    };

    const contentStream = lines.join('\n');
    const fontId = addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
    const contentId = addObject(`<< /Length ${contentStream.length} >>\nstream\n${contentStream}\nendstream`);
    const pageId = addObject(
        `<< /Type /Page /Parent 4 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 ${fontId} 0 R >> >> /Contents ${contentId} 0 R >>`
    );
    const pagesId = addObject(`<< /Type /Pages /Kids [${pageId} 0 R] /Count 1 >>`);
    const catalogId = addObject(`<< /Type /Catalog /Pages ${pagesId} 0 R >>`);

    let pdf = '%PDF-1.4\n';
    const offsets = [0];

    objects.forEach((object, index) => {
        offsets.push(pdf.length);
        pdf += `${index + 1} 0 obj\n${object}\nendobj\n`;
    });

    const xrefOffset = pdf.length;
    pdf += `xref\n0 ${objects.length + 1}\n`;
    pdf += '0000000000 65535 f \n';

    offsets.slice(1).forEach((offset) => {
        pdf += `${String(offset).padStart(10, '0')} 00000 n \n`;
    });

    pdf += `trailer\n<< /Size ${objects.length + 1} /Root ${catalogId} 0 R >>\nstartxref\n${xrefOffset}\n%%EOF`;

    return new TextEncoder().encode(pdf);
};

const drawPdfText = (lines, text, x, y, fontSize = 10) => {
    lines.push('BT');
    lines.push(`/F1 ${fontSize} Tf`);
    lines.push('0 0 0 rg');
    lines.push(`1 0 0 1 ${x} ${y} Tm`);
    lines.push(`(${escapePdfText(text)}) Tj`);
    lines.push('ET');
};

const downloadPdf = () => {
    if (loading.value || filteredRows.value.length === 0) {
        return;
    }

    const textLines = [];
    const tableX = 70;
    const tableWidth = 470;
    const headerTopY = 640;
    const headerHeight = 24;
    const rowHeight = 24;
    const columns = [55, 145, 180, 90];
    const title = 'Admin Students Report';
    const titleX = 202;
    const titleY = 700;

    drawPdfText(textLines, title, titleX, titleY, 18);

    const totalRows = filteredRows.value.length;
    const tableHeight = headerHeight + (totalRows * rowHeight);
    const tableBottomY = headerTopY - tableHeight;
    const columnEdges = [tableX];

    columns.forEach((width) => {
        columnEdges.push(columnEdges[columnEdges.length - 1] + width);
    });

    textLines.push('0.86 0.89 0.93 rg');
    textLines.push(`${tableX} ${headerTopY - headerHeight} ${tableWidth} ${headerHeight} re f`);

    textLines.push('0.75 0.78 0.82 RG');
    textLines.push('0.8 w');
    textLines.push(`${tableX} ${tableBottomY} ${tableWidth} ${tableHeight} re S`);

    for (let i = 1; i < columnEdges.length - 1; i += 1) {
        const x = columnEdges[i];
        textLines.push(`${x} ${tableBottomY} m ${x} ${headerTopY} l S`);
    }

    for (let i = 1; i <= totalRows; i += 1) {
        const y = headerTopY - headerHeight - (i * rowHeight);
        textLines.push(`${tableX} ${y} m ${tableX + tableWidth} ${y} l S`);
    }

    drawPdfText(textLines, 'No.', tableX + 8, headerTopY - 16, 10);
    drawPdfText(textLines, 'Student #', columnEdges[1] + 8, headerTopY - 16, 10);
    drawPdfText(textLines, 'Full Name', columnEdges[2] + 8, headerTopY - 16, 10);
    drawPdfText(textLines, 'Program', columnEdges[3] + 8, headerTopY - 16, 10);

    filteredRows.value.forEach((row, index) => {
        const textY = headerTopY - headerHeight - (index * rowHeight) - 16;
        drawPdfText(textLines, formatPdfCell(index + 1, 3), tableX + 8, textY, 10);
        drawPdfText(textLines, formatPdfCell(row.student_number || '-', 18), columnEdges[1] + 8, textY, 10);
        drawPdfText(textLines, formatPdfCell(row.full_name || '-', 24), columnEdges[2] + 8, textY, 10);
        drawPdfText(textLines, formatPdfCell(row.program_name || 'N/A', 12), columnEdges[3] + 8, textY, 10);
    });

    const pdfBytes = buildPdfBytes(textLines);
    const blob = new Blob([pdfBytes], { type: 'application/pdf' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'Student_List.pdf';
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
};

onMounted(loadStudents);
</script>
