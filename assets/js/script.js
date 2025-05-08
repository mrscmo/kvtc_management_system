/**
 * Main JavaScript file for Vocational Training Center SMS
 */

// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
    
    // Course form handling
    const courseForm = document.getElementById('courseForm');
    if (courseForm) {
        courseForm.addEventListener('submit', function(e) {
            const courseName = document.getElementById('course_name');
            const duration = document.getElementById('duration');
            const courseFee = document.getElementById('course_fee');
            
            let isValid = true;
            
            // Basic validation
            if (courseName.value.trim() === '') {
                setInvalid(courseName, 'Course name is required');
                isValid = false;
            } else {
                setValid(courseName);
            }
            
            if (duration.value.trim() === '') {
                setInvalid(duration, 'Duration is required');
                isValid = false;
            } else {
                setValid(duration);
            }
            
            if (courseFee.value.trim() === '' || isNaN(courseFee.value) || parseFloat(courseFee.value) <= 0) {
                setInvalid(courseFee, 'Please enter a valid course fee');
                isValid = false;
            } else {
                setValid(courseFee);
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    }
    
    // Batch form handling
    const batchForm = document.getElementById('batchForm');
    if (batchForm) {
        // Populate batch name when course is selected
        const courseSelect = document.getElementById('course_id');
        const batchName = document.getElementById('batch_name');
        
        if (courseSelect && batchName) {
            courseSelect.addEventListener('change', function() {
                const courseText = courseSelect.options[courseSelect.selectedIndex].text;
                const batchNumber = document.getElementById('batch_number').value;
                if (batchNumber.trim() !== '') {
                    batchName.value = courseText + ' - Batch ' + batchNumber;
                }
            });
            
            const batchNumber = document.getElementById('batch_number');
            if (batchNumber) {
                batchNumber.addEventListener('input', function() {
                    const courseText = courseSelect.options[courseSelect.selectedIndex].text;
                    if (courseText && this.value.trim() !== '') {
                        batchName.value = courseText + ' - Batch ' + this.value;
                    }
                });
            }
        }
        
        // Date validation
        const startDate = document.getElementById('start_date');
        const endDate = document.getElementById('end_date');
        const preAssignmentDate = document.getElementById('pre_assignment_date');
        const finalAssignmentDate = document.getElementById('final_assignment_date');
        
        if (endDate && startDate) {
            endDate.addEventListener('change', function() {
                if (new Date(startDate.value) > new Date(this.value)) {
                    setInvalid(this, 'End date cannot be before start date');
                } else {
                    setValid(this);
                }
            });
        }
        
        if (preAssignmentDate && startDate) {
            preAssignmentDate.addEventListener('change', function() {
                if (new Date(startDate.value) > new Date(this.value)) {
                    setInvalid(this, 'Pre-assignment date cannot be before start date');
                } else {
                    setValid(this);
                }
            });
        }
        
        if (finalAssignmentDate && endDate) {
            finalAssignmentDate.addEventListener('change', function() {
                if (new Date(this.value) > new Date(endDate.value)) {
                    setInvalid(this, 'Final assignment date cannot be after end date');
                } else {
                    setValid(this);
                }
            });
        }
    }
    
    // Student form handling
    const studentForm = document.getElementById('studentForm');
    if (studentForm) {
        const batchSelect = document.getElementById('batch_id');
        if (batchSelect) {
            batchSelect.addEventListener('change', function() {
                const batchId = this.value;
                if (batchId) {
                    // Fetch course details for the selected batch
                    fetch(`/vocational_training_center/ajax/get_batch_details.php?batch_id=${batchId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Auto-populate course details
                                document.getElementById('course_id').value = data.course_id;
                                document.getElementById('duration').value = data.duration;
                                document.getElementById('course_fee').value = data.course_fee;
                            }
                        })
                        .catch(error => console.error('Error fetching batch details:', error));
                }
            });
        }
        
        // Payment section handling
        const addPaymentBtn = document.getElementById('addPaymentBtn');
        if (addPaymentBtn) {
            addPaymentBtn.addEventListener('click', function() {
                const paymentForm = document.getElementById('paymentForm');
                paymentForm.classList.toggle('d-none');
            });
        }
        
        // Fee type selection handling
        const feeTypeSelect = document.getElementById('fee_type');
        const otherFeeName = document.getElementById('other_fee_name_container');
        if (feeTypeSelect && otherFeeName) {
            feeTypeSelect.addEventListener('change', function() {
                if (this.value === 'other_fee') {
                    otherFeeName.classList.remove('d-none');
                } else {
                    otherFeeName.classList.add('d-none');
                }
            });
        }
    }
    
    // Finance section handling
    const reportDateRange = document.getElementById('reportDateRange');
    if (reportDateRange) {
        reportDateRange.addEventListener('change', function() {
            const reportForm = this.closest('form');
            if (reportForm) {
                reportForm.submit();
            }
        });
    }
    
    // Export to PDF functionality
    const exportPdfBtn = document.querySelectorAll('.export-pdf');
    if (exportPdfBtn) {
        exportPdfBtn.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const url = this.getAttribute('href');
                window.open(url, '_blank');
            });
        });
    }
    
    // Helper functions
    function setInvalid(element, message) {
        element.classList.add('is-invalid');
        
        // Create or update feedback element
        let feedback = element.nextElementSibling;
        if (!feedback || !feedback.classList.contains('invalid-feedback')) {
            feedback = document.createElement('div');
            feedback.classList.add('invalid-feedback');
            element.parentNode.insertBefore(feedback, element.nextSibling);
        }
        
        feedback.textContent = message;
    }
    
    function setValid(element) {
        element.classList.remove('is-invalid');
        element.classList.add('is-valid');
        
        // Remove feedback if it exists
        const feedback = element.nextElementSibling;
        if (feedback && feedback.classList.contains('invalid-feedback')) {
            feedback.textContent = '';
        }
    }
});