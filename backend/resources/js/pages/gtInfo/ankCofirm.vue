<template>
    <div id="container">
        <div id="contents">
            <!-- 固定ヘッダーエリア -->
            <div class="fixed-header-wrapper">
                <header class="top-fixed-bar" id="topFixedBar">
                    <div class="bar-content">
                        <div class="bar-left">
                            <h1 class="bar-title">回答データ確認画面</h1>
                        </div>
                        <div class="bar-right">
                            <div class="header-actions">

                                <button class="btn-primary small-btn">回答完了者ダウンロード</button>
                            </div>
                        </div>
                    </div>
                </header>
            </div>

            <!-- メインコンテンツエリア -->
            <div class="article_confirm" style="padding-top: 30px; padding-left: 20px; padding-right: 20px;">
                <!-- 検索条件エリア -->
                <div class="search-section">
                    <h2 class="medium">検索条件</h2>
                    <div class="search-grid">
                        <!-- 左側：検索条件 + 出力対象設問（两块布局） -->
                        <div class="search-left-wrapper">
                            <!-- 左側上：検索条件 -->
                            <div class="search-left">
                                <table class="settingTable">
                                    <tbody>
                                    <tr>
                                        <th class="condition">回答者ID</th>
                                        <td>
                                            <input
                                                type="text"
                                                class="input-default"
                                                placeholder="回答者IDを入力"
                                                v-model="form.sampleNos"
                                            >
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="condition">ソース順</th>
                                        <td>
                                            <div style="display: flex; gap: 15px; align-items: center;">
                                                <label style="display: flex; align-items: center; cursor: pointer;">
                                                    <input type="radio" name="sourceOrder" value="1"  style="margin-right: 5px;"
                                                           v-model="form.sort"
                                                   checked > 昇順
                                                </label>
                                                <label style="display: flex; align-items: center; cursor: pointer;">
                                                    <input type="radio" name="sourceOrder" value="2" style="margin-right: 5px;"
                                                           v-model="form.sort"
                                                    > 降順
                                                </label>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="condition">回答者条件式</th>
                                        <td>

                                           <textarea
                                               class="input-default"
                                               placeholder="回答者条件式を入力してください（複数行可）"
                                               style="width: 100%; box-sizing: border-box; min-height: 80px; resize: vertical;"
                                               rows="3"
                                               v-model="form.condition"
                                           ></textarea>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                                <button class="btn-primary search-btn" style="margin-top: 10px;"
                                        @click="searchSubmit(1)"
                                >検索</button>

                                <!-- 左側下：出力対象設問（移動到検索按钮下方） -->
                                <div class="selection-middle" style="margin-top: 20px;">
                                    <div class="selection-header">
                                        <span>出力対象設問</span>
                                        <button class="btn-clear small-btn" @click="toggleAllCheck">
                                            {{ isAllChecked ? '全チェックOFF' : '全チェックON' }}
                                        </button>
                                    </div>
                                    <div class="checkbox-list">
                                        <div v-for="(item, idx) in questionList" :key="idx" class="checkbox-item">
                                            <input type="checkbox" :id="`q-${idx}`" :checked="isAllChecked">
                                            <label :for="`q-${idx}`">{{ item.qNo }}</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 右側：回答者リスト（一块布局） -->
                        <div class="respondent-right">
                            <div class="respondent-header">
                                <div class="respondent-count"
                                     v-if="respondentList.length>0"
                                >{{page.page}}～{{page.page+page.per_page-1}} / {{page.total}}件</div>
<!--                                <div class="respondent-percent">80%</div>-->
                            </div>
                            <div class="respondent-list">
                                <table class="answerTable">
                                    <thead>
                                    <tr>
                                        <th class="number">No</th>
                                        <th class="condition">回答者ID</th>
                                        <th class="condition">回答完了日時</th>
                                        <th class="condition">回答確認</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-for="(item, idx) in respondentList" :key="idx" class="answer-row-even">
                                        <td class="number">{{ idx + 1 }}</td>
                                        <td>{{ item.sample_no }}</td>
                                        <td>{{ item.update_date }}</td>
                                        <td>
                                            <button class="btn-primary small-btn">回答確認</button>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                            <!-- ページネーション -->
                            <div class="pagination-wrapper" style="margin-top: 10px; text-align: center;"
                            v-if="respondentList.length>0"
                            >
                                <button
                                    class="pagination-btn prev-page-btn"
                                    :disabled="page.page <= 1"
                                    @click.prevent="searchSubmit(1,1)"
                                >
                                    トップ
                                </button>
                                <button class="pagination-btn prev-page-btn"
                                        :disabled="page.page <= 1"
                                        @click.prevent="searchSubmit(page.page-1,1)"

                                >前へ</button>
                                <div class="pagination-numbers">
                                    <!-- 現在のページを表示 -->
                                    <span class="page-number active">{{ page.page }}</span>

                                    <!-- 総ページ数を計算 -->
                                    <template v-if="getTotalPages(page) > 1">
                                        <!-- 次のページを表示（存在する場合） -->
                                        <span
                                            v-if="page.page + 1 <= getTotalPages(page.page)"
                                            class="page-number"
                                            @click="searchSubmit(page.page + 1,1)"
                                        >
                            {{ page.page + 1 }}
                        </span>

                                        <!-- 省略記号（総ページ数が現在のページ+2より大きい場合に表示） -->
                                        <span
                                            v-if="page.page + 2 < getTotalPages(page)"
                                            class="page-ellipsis"
                                        >
                            ...
                        </span>

                                        <!-- 最後のページ -->
                                        <span
                                            v-if="page.page < getTotalPages(page)"
                                            class="page-number"
                                            @click="searchSubmit(getTotalPages(page),1)"
                                        >
                            {{ getTotalPages(page) }}
                        </span>
                                    </template>
                                </div>
                                <button class="pagination-btn next-page-btn"
                                        :disabled="page.page >= getTotalPages(page)"
                                        @click.prevent="searchSubmit(page.page + 1,1)"
                                >次へ</button>
                                <button
                                    class="pagination-btn next-page-btn"
                                    :disabled="page.page >= getTotalPages(page)"
                                    @click.prevent="searchSubmit(getTotalPages(page),1)"
                                >
                                    最後
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <GlobalLoading :visible="loading" />
</template>

<script setup>
import GlobalLoading from './common.vue'
import useSurveyGraph from './ankConfirm.js';
import { onMounted } from 'vue';

const {
    fetchData,
    questionList,
    loading,
    toggleAllCheck,
    isAllChecked,
    form,
    searchSubmit,
    respondentList,
    page,
    getTotalPages

} = useSurveyGraph();

onMounted(() => {
    fetchData();
});
</script>

<style scoped>
/* 既存スタイルの拡張 */
@import '../../../css/index.css';

/* ヘッダーアクションボタン */
.header-actions {
    display: flex;
    gap: 8px;
}

.small-btn {
    padding: 4px 8px;
    font-size: 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

/* 検索セクション */
.search-section {
    margin-bottom: 20px;
}

/* 核心布局调整：左（550px）+ 右（剩余）两栏布局（左侧加宽、右侧收窄） */
.search-grid {
    display: grid;
    /* 关键修改：左侧从400px改为550px，右侧自动适配剩余宽度 */
    grid-template-columns: 550px 1fr;
    gap: 30px;
}

/* 左側整体容器 */
.search-left-wrapper {
    display: flex;
    flex-direction: column;
    gap: 0;
}

/* 左側検索条件 */
.search-left {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 4px;
    width: 100%;
}

/* 検索按钮样式强化 */
.search-btn {
    width: 100%;
    padding: 8px 0;
    font-size: 14px;
}

.input-default {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-sizing: border-box;
}

/* 出力対象設問区域（検索按钮下方）- 适配左侧加宽后的布局 */
.selection-middle {
    background: #e9ecef;
    padding: 15px;
    border-radius: 4px;
    width: 100%;
    box-sizing: border-box;
}

.selection-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid #ddd;
}

/* 关键修改：左侧加宽后，复选框改为3列展示更合理 */
.checkbox-list {
    max-height: 400px;
    overflow-y: auto;
    display: grid;
    grid-template-columns: repeat(3, 1fr); /* 从2列改为3列 */
    gap: 8px;
    padding-right: 5px;
}

.checkbox-item {
    display: flex;
    align-items: center;
    font-size: 12px;
}

.checkbox-item input {
    margin-right: 4px;
}

/* 右側回答者リスト - 调整最小宽度，适配收窄后的布局 */
.respondent-right {
    display: flex;
    flex-direction: column;
    width: 100%;
    /* 关键修改：右侧最小宽度从700px改为500px，收窄显示 */
    min-width: 500px;
}

.respondent-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    font-size: 12px;
}

.respondent-list {
    flex: 1;
    overflow-y: auto;
    max-height: 700px;
    border: 1px solid #eee;
    border-radius: 4px;
}

/* テーブルスタイル調整 */
.settingTable {
    width: 100%;
    margin-bottom: 0;
}

.settingTable th {
    padding: 8px 10px;
    text-align: left;
    width: 120px;
}

.settingTable td {
    padding: 8px 10px;
    width: calc(100% - 120px);
}

.answerTable {
    width: 100%;
    border-collapse: collapse;
}

.answerTable th, .answerTable td {
    padding: 10px;
    border: 1px solid #eee;
    text-align: center;
}

.answerTable th.number {
    width: 60px;
}

/* ボタンスタイル補強 */
.btn-clear {
    background-color: #6c757d;
    color: white;
}





/* レスポンシブ対応 - 适配小屏幕 */
@media (max-width: 1200px) {
    .search-grid {
        grid-template-columns: 1fr;
    }

    .checkbox-list {
        grid-template-columns: repeat(4, 1fr);
    }

    .respondent-right {
        min-width: 100%;
    }
}
</style>
