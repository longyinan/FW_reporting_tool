// survey-graph.js
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import axios from 'axios';

// コンポーネントで使用する変数と関数をすべてエクスポート
export default function useSurveyGraph() {
    // 1. リアクティブ変数
    const displayStatus = ref(false);
    const quotaList = ref([]);
    const questionList = ref([]);
    const researchNo = ref('');
    const surveyTitle = ref('');
    const loading = ref(false);
    const showModal  = ref(false);
    const crossInfo =   ref([]);
    const selectedQCol = ref('');       // 选中した設問 qCol
    const selectedCategory = ref('');   // 选中したカテゴリ

    const selectedQColSave = ref('');
    const selectedCategorySave = ref('');

    const resetFilter = () => {
        selectedQCol.value = '';
        selectedCategory.value = '';
    };
    // 2. 定数定義
    const appEl = document.getElementById('app');
    const id = appEl?.dataset.id || '';
    const url = appEl?.dataset.url || '';
    const requestUrl = `${url}/api/ansGraph/${id}/showGraph`;
    const requestFaUrl = `${url}/api/ansGraph/${id}/showFaGraph`;
    const requestInfoUrl = `${url}/ansGraph/${id}`;
    const requestShowCrossUrl = `${url}/api/ansGraph/${id}/showCross`;

    watch(selectedQCol, (newVal) => {
        if (newVal === '') {
            selectedCategory.value = '';
        }
    });
    // 3. コア関数 - 基本データを取得
    const fetchData = async () => {
        try {
            loading.value = true;
            const response = await axios.get(requestInfoUrl);
            const data = response.data;
            researchNo.value = data.enquete.nxs_ank_name;
            surveyTitle.value = data.enquete.nxs_enquete_name;
            quotaList.value = data.quotaList;
            questionList.value = data.questionList;
        } catch (error) {
            console.error('データリクエストに失敗しました:', error);
            alert('データの読み込みに失敗しました');
        } finally {
            loading.value = false;
        }
    };

    // 4. 算出プロパティ
    const totalRejectCount = computed(() => {
        let total = 0;
        quotaList.value.forEach(quota => {
            quota.cell_infos?.forEach(cell => {
                total += Number(cell.num_target_samples) || 0;
            });
        });
        return total > 0 ? total : '-';
    });

    const currentCategories = computed(() => {
        if (!selectedQCol.value) return [];

        const allQuestions = getQuestionOptions(questionList.value);
        const target = allQuestions.find(q => q.qCol === selectedQCol.value);
        return target?.categories || [];
    });

    // 5. 補助関数 - クロス集計オプションを取得
    const getCrossOptions = (questionList, currentQuestion) => {
        return Array.isArray(questionList)
            ? questionList
                .map((q, i) => ({ q, originalIndex: i }))
                .filter(item =>
                    item.q.qNo !== currentQuestion.qNo &&
                    item.q.type !== 'NU' &&
                    item.q.type !== 'FA'
                )
            : [];
    };

    // 6. コア関数 - 回答データを表示
    const displayAnswerData = async (filter={
        'colname':'',
        'value':''
    }) => {
        displayStatus.value = true;

        if (questionList.value && questionList.value.length > 0) {
            for (const question of questionList.value) {
                if (question.type == 'FA' || question.type == 'NU') {
                    question.categories.forEach(categorie => {
                        const faData = {
                            'target_column': `${question.qCol}_${categorie.catNo}`,
                            'page': 1,
                            'per_page': 10,
                        };
                        if(filter.colname){
                            faData.filter=filter;
                        }

                        handleQuestionRequest(faData, requestFaUrl).then(res => {
                            categorie.items = res.items;
                            categorie.pagination = res.pagination;
                        });
                    });
                } else {
                    if(filter.colname){
                        question.filter=filter;
                    }
                    handleQuestionRequest(question, requestUrl).then(res => {
                        if (question.type == 'SA' || question.type == 'MA') {
                            const targetQuestion = questionList.value.find(item => item.qCol === res.qCol);
                            res.categories.forEach(resCat => {
                                const targetCat = targetQuestion.categories.find(cat => cat.catNo === resCat.catNo);
                                if (targetCat) {
                                    targetCat.count = resCat.count || 0;
                                    targetCat.rate = resCat.rate || 0;
                                }
                            });
                        } else if (question.type != 'SA' && question.type != 'MA' && question.type != 'FA' && question.type != 'NU') {
                            const targetGroipQuestion = questionList.value.find(item => item.qNo === question.qNo);
                            res.forEach(resQuestion => {
                                const targetQuestion = targetGroipQuestion.subQuestions.find(item => item.qCol === resQuestion.qCol);
                                if (targetQuestion.type == 'SA' || targetQuestion.type == 'MA') {
                                    resQuestion.categories.forEach(resCat => {
                                        const targetCat = targetQuestion.categories.find(cat => cat.catNo === resCat.catNo);
                                        if (targetCat) {
                                            targetCat.count = resCat.count || 0;
                                            targetCat.rate = resCat.rate || 0;
                                        }
                                    });
                                }
                            });

                            question.subQuestions.forEach(subQuestion => {
                                if (subQuestion.type == 'FA' || subQuestion.type == 'NU') {
                                    subQuestion.categories.forEach(categorie => {
                                        const faData = {
                                            'target_column': `${subQuestion.qCol}_${categorie.catNo}`,
                                            'page': 1,
                                            'per_page': 10,
                                        };
                                        if(filter.colname){
                                            faData.filter=filter;
                                        }
                                        handleQuestionRequest(faData, requestFaUrl).then(res => {
                                            categorie.items = res.items;
                                            categorie.pagination = res.pagination;
                                        });
                                    });
                                } else {
                                    subQuestion.categories.forEach(cat => {
                                        if (cat.otherFa.length > 0) {
                                            cat.otherFa.forEach((other, otherIdx) => {
                                                const faData = {
                                                    'target_column': `${subQuestion.qCol}_snt${cat.catNo}_${otherIdx + 1}`,
                                                    'page': 1,
                                                    'per_page': 10,
                                                };
                                                if(filter.colname){
                                                    faData.filter=filter;
                                                }
                                                handleQuestionRequest(faData, requestFaUrl).then(res => {
                                                    other.items = res.items;
                                                    other.pagination = res.pagination;
                                                });
                                            });
                                        }
                                    });
                                }
                            });
                        }
                    });

                    if (question.type == 'SA' || question.type == 'MA') {
                        question.categories.forEach(cat => {
                            if (cat.otherFa.length > 0) {
                                cat.otherFa.forEach((other, otherIdx) => {
                                    const faData = {
                                        'target_column': `${question.qCol}_snt${cat.catNo}_${otherIdx + 1}`,
                                        'page': 1,
                                        'per_page': 10,
                                    };
                                    if(filter.colname){
                                        faData.filter=filter;
                                    }
                                    handleQuestionRequest(faData, requestFaUrl).then(res => {
                                        other.items = res.items;
                                        other.pagination = res.pagination;
                                    });
                                });
                            }
                        });
                    }
                }
            }
        }
    };

    // 7. ラップ関数 - リクエストを処理
    const handleQuestionRequest = async (question, url) => {
        try {
            const requestData = question;
            const response = await axios.post(url, requestData, {
                headers: { 'Content-Type': 'application/json' }
            });
            return response.data;
        } catch (error) {
            console.error(`問題${question.no}のリクエストに失敗しました`, error);
            throw new Error(`Question-${question.no}: ${error.message}`);
        }
    };

    // 8. ページ切り替え関数
    const changePage = async (question, targetPage, ansIdx, type = 'cat', qCol = '') => {
        try {
            loading.value = true;
            let pagination = {};
            if (type == 'other') {
                pagination = question.otherFa[ansIdx].pagination;
            } else {
                pagination = question.categories[ansIdx].pagination;
            }

            const totalPages = getTotalPages(pagination);
            if (targetPage < 1 || targetPage > totalPages) return;

            let faData = {};
            if (type == 'other') {
                faData = {
                    'target_column': `${qCol}_snt${question.catNo}_${ansIdx + 1}`,
                    'page': targetPage,
                    'per_page': pagination.per_page || 10,
                };
            } else {
                faData = {
                    'target_column': `${question.qCol}_${question.categories[ansIdx].catNo}`,
                    'page': targetPage,
                    'per_page': pagination.per_page || 10,
                };
            }
            if(selectedQColSave.value){
                faData.filter = {
                    'colname':selectedQColSave.value,
                    'value':selectedCategorySave.value
                }
            }
            const response = await axios.post(
                requestFaUrl,
                faData,
                { headers: { 'Content-Type': 'application/json' } }
            );

            if (response.data && response.data.items) {
                if (type == 'other') {
                    question.otherFa[ansIdx].items = response.data.items;
                    question.otherFa[ansIdx].pagination = response.data.pagination;
                } else {
                    question.categories[ansIdx].items = response.data.items;
                    question.categories[ansIdx].pagination = response.data.pagination;
                }
            }
        } catch (error) {
            console.error('ページングリクエストに失敗しました:', error);
            alert('ページの切り替えに失敗しました');
        } finally {
            loading.value = false;
        }
    };

    // 9. 補助関数 - 合計数を取得
    const getTotalCount = (categories) => {
        if (!categories || !Array.isArray(categories)) return 0;
        return categories.reduce((sum, item) => {
            const count = Number(item.count);
            return sum + (isNaN(count) ? 0 : count);
        }, 0);
    };

    // 10. 補助関数 - 総ページ数を取得
    const getTotalPages = (pagination) => {
        if (!pagination || !pagination.total || !pagination.per_page) return 1;
        return Math.ceil(pagination.total / pagination.per_page);
    };

    // 11. スクロール処理関連
    const topFixedBar = ref(null);
    const handleScroll = () => {
        const currentScrollTop = window.pageYOffset || document.documentElement.scrollTop;
        if (currentScrollTop <= 0) {
            topFixedBar.value?.classList.remove('hidden');
        } else {
            topFixedBar.value?.classList.add('hidden');
        }
    };

    // 12. ライフサイクル関数
    const init = () => {
        fetchData();
        topFixedBar.value = document.querySelector('.top-fixed-bar');
        window.addEventListener('scroll', handleScroll);
        handleScroll();
    };

    const destroy = () => {
        window.removeEventListener('scroll', handleScroll);
    };

    // 13. クロス集計関数
    const showCross = async (qNoH, qNoS) => {
        console.log(qNoH)
        console.log(qNoS)
        try {
            loading.value = true;
            const postData = {
                'sideQno': qNoS,
                'headQno': qNoH,
            };
            const response = await axios.post(
                requestShowCrossUrl,
                postData,
                { headers: { 'Content-Type': 'application/json' } }
            );
            const data = response.data;
            crossInfo.value = data
            openModal()

        } catch (error) {
            console.error('データリクエストに失敗しました:', error);
            alert('データの読み込みに失敗しました');
        } finally {
            loading.value = false;
        }
    };

    const getQuestionOptions = (questionList) => {
        const result = [];


        questionList.forEach(question => {
            const { type, subQuestions } = question;
            if (type === 'SA' || type === 'MA') {
                result.push(question);
            }
            else if (type !== 'FA' && type !== 'NU' && subQuestions && subQuestions.length) {
                subQuestions.forEach(sub => {
                    if (sub.type === 'SA' || sub.type === 'MA') {
                        result.push(sub);
                    }
                });
            }
        });

        return result;
    };
    const openModal =  () => {
        showModal.value=true;
    }

    const closeModal =  () => {
        showModal.value=false;
    }

    // フィルタボタン：選択した設問とカテゴリを取得
    const applyFilter = () => {
        // 取得したい値
        const qCol = selectedQCol.value;
        const catNo = selectedCategory.value;

        const hasBoth = qCol && catNo;
        const hasNone = !qCol && !catNo;

        if (!hasBoth && !hasNone) {
            alert('設問とカテゴリを両方選択してください。');
            return;
        }
        displayAnswerData({
            'colname':qCol,
            'value':catNo,

        })
        selectedQColSave.value = qCol;
        selectedCategorySave.value = catNo;

    };
    // コンポーネントで使用する変数とメソッドをすべてエクスポート
    return {
        displayStatus,
        quotaList,
        questionList,
        researchNo,
        surveyTitle,
        loading,
        totalRejectCount,
        currentCategories,
        selectedQCol,
        selectedCategory,
        getCrossOptions,
        displayAnswerData,
        changePage,
        getTotalCount,
        getTotalPages,
        showCross,
        init,
        destroy,
        openModal,
        closeModal,
        showModal,
        crossInfo,
        getQuestionOptions,
        resetFilter,
        applyFilter
    };
}
